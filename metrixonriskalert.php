<?php
/**
 * Metrixon Risk Alert System for PrestaShop.
 *
 * @author Metrixon
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/MetrixonRiskConfig.php';
require_once __DIR__ . '/classes/MetrixonRiskAlertInstaller.php';
require_once __DIR__ . '/classes/MetrixonRiskAlertRepository.php';
require_once __DIR__ . '/classes/MetrixonRiskAlertPrestashopAdapter.php';
require_once __DIR__ . '/classes/MetrixonRiskAlertPolicy.php';
require_once __DIR__ . '/classes/MetrixonRiskAlertService.php';

class MetrixonRiskAlert extends Module
{
    public function __construct()
    {
        $this->name = 'metrixonriskalert';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Metrixon';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array('min' => '1.7.7.0', 'max' => _PS_VERSION_);

        parent::__construct();

        $this->displayName = $this->l('Metrixon Risk Alert System');
        $this->description = $this->l('Deterministic bestseller stockout risk alerts with explainable recommendations.');
        $this->confirmUninstall = $this->l('Uninstall Metrixon Risk Alert System? Alert history will be removed.');
    }

    public function install()
    {
        $installer = new MetrixonRiskAlertInstaller($this);

        return parent::install()
            && $installer->install()
            && MetrixonRiskConfig::installDefaults()
            && $this->installAdminTabs()
            && $this->registerHook('displayBackOfficeHeader');
    }

    public function uninstall()
    {
        $installer = new MetrixonRiskAlertInstaller($this);

        return $this->uninstallAdminTabs()
            && $installer->uninstall()
            && MetrixonRiskConfig::deleteAll()
            && parent::uninstall();
    }

    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMetrixonRiskAlertSettings'));
    }

    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('controller') === 'AdminMetrixonRiskAlert'
            || Tools::getValue('controller') === 'AdminMetrixonRiskAlertSettings') {
            $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
        }
    }

    public function getCronUrl()
    {
        return $this->context->link->getModuleLink(
            $this->name,
            'cron',
            array('token' => MetrixonRiskConfig::get('CRON_TOKEN')),
            true
        );
    }

    private function installAdminTabs()
    {
        $parentId = (int) Tab::getIdFromClassName('AdminCatalog');
        if (!$parentId) {
            $parentId = 0;
        }

        return $this->installTab('AdminMetrixonRiskAlert', $this->l('Risk Alerts'), $parentId)
            && $this->installTab('AdminMetrixonRiskAlertSettings', $this->l('Risk Alert Settings'), $parentId);
    }

    private function installTab($className, $name, $parentId)
    {
        if ((int) Tab::getIdFromClassName($className)) {
            return true;
        }

        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = $className;
        $tab->id_parent = (int) $parentId;
        $tab->module = $this->name;
        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = $name;
        }

        return (bool) $tab->add();
    }

    private function uninstallAdminTabs()
    {
        $result = true;
        foreach (array('AdminMetrixonRiskAlert', 'AdminMetrixonRiskAlertSettings') as $className) {
            $idTab = (int) Tab::getIdFromClassName($className);
            if ($idTab) {
                $tab = new Tab($idTab);
                $result = $result && (bool) $tab->delete();
            }
        }

        return $result;
    }
}
