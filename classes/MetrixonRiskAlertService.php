<?php
/**
 * Orchestrates adapter sync, risk policy evaluation, and lifecycle transitions.
 *
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MetrixonRiskAlertService
{
    private $adapter;
    private $policy;
    private $repository;

    public function __construct(
        MetrixonRiskAlertPrestashopAdapter $adapter = null,
        MetrixonRiskAlertPolicy $policy = null,
        MetrixonRiskAlertRepository $repository = null
    ) {
        $this->adapter = $adapter ?: new MetrixonRiskAlertPrestashopAdapter();
        $this->policy = $policy ?: new MetrixonRiskAlertPolicy(MetrixonRiskConfig::policy());
        $this->repository = $repository ?: new MetrixonRiskAlertRepository();
    }

    public function evaluateShop($idShop, $idLang = null)
    {
        $policyConfig = MetrixonRiskConfig::policy();
        $inputs = $this->adapter->getCatalogRiskInputs($idShop, $policyConfig);
        $decisions = array();
        $emitted = 0;

        $this->repository->wakeExpiredSnoozes($idShop);

        foreach ($inputs as $input) {
            $decision = $this->policy->evaluateInput($input);
            $variant = $input['variant'];
            $this->repository->upsertSnapshot($idShop, $variant, $decision['snapshot']);

            $variantKey = $decision['variant_key'];
            $decisions[$variantKey] = $decision;
            if (!$decision['qualifies']) {
                continue;
            }

            $existing = $this->repository->findOpenAlert(
                $idShop,
                $variant['id_product'],
                $variant['id_product_attribute']
            );

            if ($existing && $this->policy->canEmit($decision, $existing, $this->repository->countActiveAlerts($idShop))) {
                $this->repository->refreshAlert($idShop, (int) $existing['id_metrixon_risk_alert'], $variant, $decision);
                ++$emitted;
                continue;
            }

            if ($existing) {
                continue;
            }

            $recent = $this->repository->findLatestAlert(
                $idShop,
                $variant['id_product'],
                $variant['id_product_attribute']
            );
            if ($recent && !$this->policy->canEmit($decision, $recent, $this->repository->countActiveAlerts($idShop))) {
                continue;
            }

            if ($this->repository->countActiveAlerts($idShop) >= $this->policy->getMaxActiveAlerts()) {
                continue;
            }

            $this->repository->createAlert($idShop, $variant, $decision);
            ++$emitted;
        }

        $this->repository->resolveNoLongerQualifying($idShop, $decisions);
        $this->repository->recordEvent($idShop, null, null, 'evaluation_completed', null, array(
            'variants_evaluated' => count($inputs),
            'eligible_variants' => count($decisions),
            'alerts_created_or_refreshed' => $emitted,
        ));

        return array(
            'variants_evaluated' => count($inputs),
            'eligible_variants' => count($decisions),
            'alerts_created_or_refreshed' => $emitted,
        );
    }

    public function getCapabilities()
    {
        return $this->adapter->getCapabilities();
    }
}
