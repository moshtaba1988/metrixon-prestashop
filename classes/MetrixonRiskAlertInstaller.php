<?php
/**
 * Installer for Metrixon Risk Alert System.
 *
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MetrixonRiskAlertInstaller
{
    /**
     * @var Module
     */
    private $module;

    public function __construct(Module $module)
    {
        $this->module = $module;
    }

    public function install()
    {
        require_once $this->module->getLocalPath() . 'sql/install.php';

        return metrixonRiskAlertInstallSql();
    }

    public function uninstall()
    {
        require_once $this->module->getLocalPath() . 'sql/uninstall.php';

        return metrixonRiskAlertUninstallSql();
    }
}
