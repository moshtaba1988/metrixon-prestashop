<?php
/**
 * Risk alert policy configuration.
 *
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'metrixonriskalert/classes/MetrixonRiskConfig.php';
require_once _PS_MODULE_DIR_ . 'metrixonriskalert/classes/MetrixonRiskAlertRepository.php';
require_once _PS_MODULE_DIR_ . 'metrixonriskalert/classes/MetrixonRiskAlertPrestashopAdapter.php';

class AdminMetrixonRiskAlertSettingsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitMetrixonRiskSettings')) {
            $repository = new MetrixonRiskAlertRepository();
            foreach (MetrixonRiskConfig::policy() as $key => $oldValue) {
                $requestKey = MetrixonRiskConfig::PREFIX . strtoupper($this->configKeyFromPolicyKey($key));
                if (Tools::getValue($requestKey) !== false) {
                    $newValue = Tools::getValue($requestKey);
                    if ((string) $oldValue !== (string) $newValue) {
                        $repository->recordPolicyChange(
                            (int) $this->context->shop->id,
                            $requestKey,
                            $oldValue,
                            $newValue,
                            (int) $this->context->employee->id
                        );
                    }
                }
            }

            MetrixonRiskConfig::updateFromRequest();
            $this->confirmations[] = $this->module->l('Settings saved.', 'AdminMetrixonRiskAlertSettingsController');
        }

        parent::postProcess();
    }

    public function initContent()
    {
        parent::initContent();

        $this->context->smarty->assign(array(
            'form_action' => self::$currentIndex . '&token=' . $this->token,
            'config' => $this->getFormValues(),
            'capabilities' => (new MetrixonRiskAlertPrestashopAdapter())->getCapabilities(),
            'cron_url' => $this->context->link->getModuleLink(
                'metrixonriskalert',
                'cron',
                array('token' => MetrixonRiskConfig::get('CRON_TOKEN'))
            ),
        ));

        $this->setTemplate('settings.tpl');
    }

    private function getFormValues()
    {
        $values = array();
        foreach (MetrixonRiskConfig::defaults() as $key => $default) {
            $values[MetrixonRiskConfig::PREFIX . $key] = MetrixonRiskConfig::get($key, $default);
        }

        return $values;
    }

    private function configKeyFromPolicyKey($key)
    {
        $map = array(
            'bestseller_percentile' => 'BESTSELLER_PERCENTILE',
            'bestseller_top_n' => 'BESTSELLER_TOP_N',
            'bestseller_mode' => 'BESTSELLER_MODE',
            'urgency_days' => 'URGENCY_DAYS',
            'materiality_floor' => 'MATERIALITY_FLOOR',
            'confidence_minimum' => 'CONFIDENCE_MINIMUM',
            'cooldown_hours' => 'COOLDOWN_HOURS',
            'max_active_alerts' => 'MAX_ACTIVE_ALERTS',
            'freshness_max_minutes' => 'FRESHNESS_MAX_MINUTES',
            'min_sale_events' => 'MIN_SALE_EVENTS',
            'default_lead_time_days' => 'DEFAULT_LEAD_TIME_DAYS',
            'default_margin_percent' => 'DEFAULT_MARGIN_PERCENT',
        );

        return isset($map[$key]) ? $map[$key] : strtoupper($key);
    }
}
