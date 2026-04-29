<?php
/**
 * Admin alert list/detail/actions controller.
 *
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'metrixonriskalert/classes/MetrixonRiskConfig.php';
require_once _PS_MODULE_DIR_ . 'metrixonriskalert/classes/MetrixonRiskAlertRepository.php';
require_once _PS_MODULE_DIR_ . 'metrixonriskalert/classes/MetrixonRiskAlertPrestashopAdapter.php';
require_once _PS_MODULE_DIR_ . 'metrixonriskalert/classes/MetrixonRiskAlertPolicy.php';
require_once _PS_MODULE_DIR_ . 'metrixonriskalert/classes/MetrixonRiskAlertService.php';

class AdminMetrixonRiskAlertController extends ModuleAdminController
{
    /**
     * @var MetrixonRiskAlertRepository
     */
    private $repository;

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->repository = new MetrixonRiskAlertRepository();
    }

    public function postProcess()
    {
        $idShop = (int) $this->context->shop->id;

        if (Tools::isSubmit('runRiskEvaluation')) {
            $service = new MetrixonRiskAlertService(null, null, $this->repository);
            $result = $service->evaluateShop($idShop, (int) $this->context->language->id);
            $this->confirmations[] = $this->module->l('Risk evaluation completed.', 'AdminMetrixonRiskAlert')
                . ' ' . sprintf(
                    $this->module->l('%d variants evaluated, %d eligible variants, %d alerts created or refreshed.', 'AdminMetrixonRiskAlert'),
                    $result['variants_evaluated'],
                    $result['eligible_variants'],
                    $result['alerts_created_or_refreshed']
                );
        }

        $idAlert = (int) Tools::getValue('id_metrixon_risk_alert');
        if ($idAlert && Tools::isSubmit('submitRiskAlertState')) {
            $state = Tools::getValue('state');
            $reason = Tools::getValue('reason');
            $allowedReasons = array('not_relevant', 'already_ordered', 'seasonal_not_applicable', 'data_inaccurate', 'other', 'merchant_resolved');

            if (($state === 'dismissed' || $state === 'snoozed') && !in_array($reason, $allowedReasons, true)) {
                $this->errors[] = $this->module->l('A valid feedback reason is required.', 'AdminMetrixonRiskAlert');
            } elseif (!$this->repository->getAlert($idAlert, $idShop)) {
                $this->errors[] = $this->module->l('Alert not found for this shop.', 'AdminMetrixonRiskAlert');
            } else {
                $meta = array();
                if ($state === 'snoozed') {
                    $hours = max(1, (int) Tools::getValue('snooze_hours', 24));
                    $meta['snoozed_until'] = date('Y-m-d H:i:s', time() + ($hours * 3600));
                }

                $actor = 'employee:' . (int) $this->context->employee->id;
                $this->repository->setState($idShop, $idAlert, $state, $reason, $meta + array('actor' => $actor));
                $this->confirmations[] = $this->module->l('Alert updated.', 'AdminMetrixonRiskAlert');
            }
        }

        parent::postProcess();
    }

    public function initContent()
    {
        parent::initContent();

        $idAlert = (int) Tools::getValue('id_metrixon_risk_alert');
        if ($idAlert) {
            $this->renderAlertDetail($idAlert);
            return;
        }

        $this->renderAlertList();
    }

    private function renderAlertList()
    {
        $idShop = (int) $this->context->shop->id;
        $state = Tools::getValue('state', 'active');
        $alerts = $this->repository->getAlerts($idShop, $state === 'all' ? null : $state, 100);

        foreach ($alerts as &$alert) {
            $alert['detail_url'] = $this->context->link->getAdminLink('AdminMetrixonRiskAlert')
                . '&id_metrixon_risk_alert=' . (int) $alert['id_metrixon_risk_alert'];
            $alert['reason_codes'] = $this->decodeJson($alert['reason_codes']);
            $alert['compiled_truth'] = $this->decodeJson($alert['compiled_truth']);
            $alert['action_recommendation'] = $this->decodeJson($alert['action_recommendation']);
        }

        $this->context->smarty->assign(array(
            'alerts' => $alerts,
            'selected_state' => $state,
            'state_url' => $this->context->link->getAdminLink('AdminMetrixonRiskAlert'),
            'settings_url' => $this->context->link->getAdminLink('AdminMetrixonRiskAlertSettings'),
            'run_url' => $this->context->link->getAdminLink('AdminMetrixonRiskAlert') . '&runRiskEvaluation=1',
        ));

        $this->setTemplate('alerts.tpl');
    }

    private function renderAlertDetail($idAlert)
    {
        $alert = $this->repository->getAlert($idAlert, (int) $this->context->shop->id);
        if (!$alert) {
            $this->errors[] = $this->module->l('Alert not found.', 'AdminMetrixonRiskAlert');
            $this->renderAlertList();
            return;
        }

        $alert['reason_codes'] = $this->decodeJson($alert['reason_codes']);
        $alert['compiled_truth'] = $this->decodeJson($alert['compiled_truth']);
        $alert['timeline'] = array_reverse($this->decodeJson($alert['timeline']));
        $alert['action_recommendation'] = $this->decodeJson($alert['action_recommendation']);
        $events = $this->repository->getEvents((int) $this->context->shop->id, (int) $alert['id_metrixon_risk_alert']);

        $this->context->smarty->assign(array(
            'alert' => $alert,
            'events' => $events,
            'back_url' => $this->context->link->getAdminLink('AdminMetrixonRiskAlert'),
            'action_url' => $this->context->link->getAdminLink('AdminMetrixonRiskAlert')
                . '&id_metrixon_risk_alert=' . (int) $alert['id_metrixon_risk_alert'],
            'feedback_reasons' => array(
                'not_relevant' => $this->module->l('Not relevant', 'AdminMetrixonRiskAlert'),
                'already_ordered' => $this->module->l('Already ordered', 'AdminMetrixonRiskAlert'),
                'seasonal_not_applicable' => $this->module->l('Seasonal / not applicable', 'AdminMetrixonRiskAlert'),
                'data_inaccurate' => $this->module->l('Data inaccurate', 'AdminMetrixonRiskAlert'),
                'other' => $this->module->l('Other', 'AdminMetrixonRiskAlert'),
                'merchant_resolved' => $this->module->l('Resolved manually', 'AdminMetrixonRiskAlert'),
            ),
        ));

        $this->setTemplate('detail.tpl');
    }

    private function decodeJson($json)
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : array();
    }
}
