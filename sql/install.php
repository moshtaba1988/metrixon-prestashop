<?php
/**
 * Copyright since 2026 Metrixon
 *
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function metrixonRiskAlertInstallSql()
{
    $engine = _MYSQL_ENGINE_;
    $prefix = _DB_PREFIX_;
    $queries = array();

    $queries[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'metrixon_risk_alert` (
        `id_metrixon_risk_alert` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `alert_uid` VARCHAR(64) NOT NULL,
        `id_shop` INT UNSIGNED NOT NULL,
        `id_product` INT UNSIGNED NOT NULL,
        `id_product_attribute` INT UNSIGNED NOT NULL DEFAULT 0,
        `sku` VARCHAR(128) NULL,
        `product_name` VARCHAR(255) NOT NULL,
        `variant_name` VARCHAR(255) NULL,
        `alert_type` VARCHAR(64) NOT NULL,
        `state` VARCHAR(16) NOT NULL,
        `severity` VARCHAR(16) NOT NULL,
        `reason_codes` TEXT NOT NULL,
        `compiled_truth` MEDIUMTEXT NOT NULL,
        `timeline` MEDIUMTEXT NOT NULL,
        `action_recommendation` TEXT NOT NULL,
        `feedback_reason` VARCHAR(64) NULL,
        `last_days_until_stockout` DECIMAL(20,6) NOT NULL DEFAULT 0,
        `last_estimated_lost_profit` DECIMAL(20,6) NOT NULL DEFAULT 0,
        `snoozed_until` DATETIME NULL,
        `resolved_streak` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `not_bestseller_since` DATETIME NULL,
        `date_add` DATETIME NOT NULL,
        `date_upd` DATETIME NOT NULL,
        PRIMARY KEY (`id_metrixon_risk_alert`),
        UNIQUE KEY `alert_uid` (`alert_uid`),
        KEY `shop_variant_state` (`id_shop`, `id_product`, `id_product_attribute`, `state`),
        KEY `shop_created` (`id_shop`, `date_add`)
    ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

    $queries[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'metrixon_risk_snapshot` (
        `id_metrixon_risk_snapshot` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_shop` INT UNSIGNED NOT NULL,
        `id_product` INT UNSIGNED NOT NULL,
        `id_product_attribute` INT UNSIGNED NOT NULL DEFAULT 0,
        `daily_velocity_7d` DECIMAL(20,6) NOT NULL DEFAULT 0,
        `daily_velocity_30d` DECIMAL(20,6) NOT NULL DEFAULT 0,
        `adjusted_daily_velocity` DECIMAL(20,6) NOT NULL DEFAULT 0,
        `days_until_stockout` DECIMAL(20,6) NOT NULL DEFAULT 0,
        `estimated_lost_profit` DECIMAL(20,6) NOT NULL DEFAULT 0,
        `confidence_score` DECIMAL(5,4) NOT NULL DEFAULT 0,
        `data_freshness_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
        `current_stock` DECIMAL(20,6) NOT NULL DEFAULT 0,
        `unit_margin` DECIMAL(20,6) NOT NULL DEFAULT 0,
        `lead_time_days` DECIMAL(20,6) NOT NULL DEFAULT 0,
        `sale_events_30d` INT UNSIGNED NOT NULL DEFAULT 0,
        `payload` MEDIUMTEXT NOT NULL,
        `computed_at` DATETIME NOT NULL,
        PRIMARY KEY (`id_metrixon_risk_snapshot`),
        UNIQUE KEY `shop_variant` (`id_shop`, `id_product`, `id_product_attribute`),
        KEY `computed_at` (`computed_at`)
    ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

    $queries[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'metrixon_risk_event` (
        `id_metrixon_risk_event` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_shop` INT UNSIGNED NOT NULL,
        `id_metrixon_risk_alert` INT UNSIGNED NULL,
        `variant_key` VARCHAR(64) NULL,
        `event_type` VARCHAR(64) NOT NULL,
        `reason` VARCHAR(64) NULL,
        `payload` MEDIUMTEXT NOT NULL,
        `date_add` DATETIME NOT NULL,
        PRIMARY KEY (`id_metrixon_risk_event`),
        KEY `shop_event_date` (`id_shop`, `event_type`, `date_add`),
        KEY `alert_event` (`id_metrixon_risk_alert`, `date_add`)
    ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

    $queries[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'metrixon_risk_policy_audit` (
        `id_metrixon_risk_policy_audit` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_shop` INT UNSIGNED NOT NULL,
        `setting_key` VARCHAR(64) NOT NULL,
        `old_value` VARCHAR(255) NULL,
        `new_value` VARCHAR(255) NULL,
        `id_employee` INT UNSIGNED NULL,
        `date_add` DATETIME NOT NULL,
        PRIMARY KEY (`id_metrixon_risk_policy_audit`),
        KEY `shop_setting_date` (`id_shop`, `setting_key`, `date_add`)
    ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

    foreach ($queries as $query) {
        if (!Db::getInstance()->execute($query)) {
            return false;
        }
    }

    return true;
}
