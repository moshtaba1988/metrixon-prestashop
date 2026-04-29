<?php
/**
 * Configuration helpers for the Metrixon Risk Alert module.
 *
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MetrixonRiskConfig
{
    const PREFIX = 'METRIXON_RA_';

    const BESTSELLER_MODE_PERCENTILE = 'percentile';
    const BESTSELLER_MODE_TOP_N = 'top_n';

    public static function defaults()
    {
        return array(
            'BESTSELLER_PERCENTILE' => 20,
            'BESTSELLER_TOP_N' => 10,
            'BESTSELLER_MODE' => self::BESTSELLER_MODE_PERCENTILE,
            'URGENCY_DAYS' => 5,
            'MATERIALITY_FLOOR' => 150,
            'CONFIDENCE_MINIMUM' => 0.70,
            'COOLDOWN_HOURS' => 24,
            'MAX_ACTIVE_ALERTS' => 5,
            'FRESHNESS_MAX_MINUTES' => 360,
            'MIN_SALE_EVENTS' => 5,
            'DEFAULT_LEAD_TIME_DAYS' => 7,
            'DEFAULT_MARGIN_PERCENT' => 30,
            'CRON_TOKEN' => Tools::passwdGen(32),
        );
    }

    public static function installDefaults()
    {
        foreach (self::defaults() as $key => $value) {
            if (!Configuration::hasKey(self::PREFIX . $key)) {
                Configuration::updateValue(self::PREFIX . $key, $value);
            }
        }

        return true;
    }

    public static function deleteAll()
    {
        foreach (array_keys(self::defaults()) as $key) {
            Configuration::deleteByName(self::PREFIX . $key);
        }

        return true;
    }

    public static function get($key, $default = null)
    {
        $value = Configuration::get(self::PREFIX . $key);

        return $value === false || $value === null || $value === '' ? $default : $value;
    }

    public static function getInt($key, $default)
    {
        return (int) self::get($key, $default);
    }

    public static function getFloat($key, $default)
    {
        return (float) self::get($key, $default);
    }

    public static function updateFromRequest()
    {
        $fields = array(
            'BESTSELLER_PERCENTILE' => 'int',
            'BESTSELLER_TOP_N' => 'int',
            'BESTSELLER_MODE' => 'string',
            'URGENCY_DAYS' => 'float',
            'MATERIALITY_FLOOR' => 'float',
            'CONFIDENCE_MINIMUM' => 'float',
            'COOLDOWN_HOURS' => 'int',
            'MAX_ACTIVE_ALERTS' => 'int',
            'FRESHNESS_MAX_MINUTES' => 'int',
            'MIN_SALE_EVENTS' => 'int',
            'DEFAULT_LEAD_TIME_DAYS' => 'float',
            'DEFAULT_MARGIN_PERCENT' => 'float',
        );

        foreach ($fields as $field => $type) {
            $value = Tools::getValue(self::PREFIX . $field);
            if ($type === 'int') {
                $value = (int) $value;
            } elseif ($type === 'float') {
                $value = (float) $value;
            } else {
                $value = pSQL($value);
            }

            Configuration::updateValue(self::PREFIX . $field, $value);
        }
    }

    public static function policy()
    {
        return array(
            'bestseller_percentile' => self::getFloat('BESTSELLER_PERCENTILE', 20),
            'bestseller_top_n' => self::getInt('BESTSELLER_TOP_N', 10),
            'top_n' => self::getInt('BESTSELLER_TOP_N', 10),
            'bestseller_mode' => self::get('BESTSELLER_MODE', self::BESTSELLER_MODE_PERCENTILE),
            'urgency_days' => self::getFloat('URGENCY_DAYS', 5),
            'urgency_threshold_days' => self::getFloat('URGENCY_DAYS', 5),
            'materiality_floor' => self::getFloat('MATERIALITY_FLOOR', 150),
            'confidence_minimum' => self::getFloat('CONFIDENCE_MINIMUM', 0.70),
            'cooldown_hours' => self::getInt('COOLDOWN_HOURS', 24),
            'max_active_alerts' => self::getInt('MAX_ACTIVE_ALERTS', 5),
            'freshness_max_minutes' => self::getInt('FRESHNESS_MAX_MINUTES', 360),
            'min_sale_events' => self::getInt('MIN_SALE_EVENTS', 5),
            'default_lead_time_days' => self::getFloat('DEFAULT_LEAD_TIME_DAYS', 7),
            'default_margin_percent' => self::getFloat('DEFAULT_MARGIN_PERCENT', 30),
        );
    }
}
