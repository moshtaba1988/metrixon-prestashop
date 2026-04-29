<?php
/**
 * Copyright since 2026 Metrixon
 *
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function metrixonRiskAlertUninstallSql()
{
    $queries = array(
        'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'metrixon_risk_policy_audit`',
        'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'metrixon_risk_event`',
        'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'metrixon_risk_snapshot`',
        'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'metrixon_risk_alert`',
    );

    foreach ($queries as $query) {
        if (!Db::getInstance()->execute($query)) {
            return false;
        }
    }

    return true;
}
