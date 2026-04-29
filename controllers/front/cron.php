<?php
/**
 * Front controller used by an external scheduler to evaluate alerts.
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

class MetrixonRiskAlertCronModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $ajax = true;

    public function initContent()
    {
        parent::initContent();

        header('Content-Type: application/json');
        $token = (string) Tools::getValue('token');
        if ($token === '' || $token !== (string) Configuration::get(MetrixonRiskConfig::PREFIX . 'CRON_TOKEN')) {
            http_response_code(403);
            die(json_encode(array('success' => false, 'error' => 'invalid_token')));
        }

        $idShop = (int) Context::getContext()->shop->id;
        $service = new MetrixonRiskAlertService();
        $result = $service->evaluateShop($idShop, (int) Context::getContext()->language->id);

        die(json_encode(array('success' => true, 'result' => $result)));
    }
}
