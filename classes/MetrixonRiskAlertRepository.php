<?php
/**
 * Persistence helpers for snapshots, alerts, KPI events, and policy audits.
 *
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MetrixonRiskAlertRepository
{
    const STATE_ACTIVE = 'active';
    const STATE_SNOOZED = 'snoozed';
    const STATE_RESOLVED = 'resolved';
    const STATE_DISMISSED = 'dismissed';

    public function upsertSnapshot($idShop, array $variant, array $snapshot)
    {
        $payload = array(
            'id_shop' => (int) $idShop,
            'id_product' => (int) $variant['id_product'],
            'id_product_attribute' => (int) $variant['id_product_attribute'],
            'daily_velocity_7d' => (float) $snapshot['daily_velocity_7d'],
            'daily_velocity_30d' => (float) $snapshot['daily_velocity_30d'],
            'adjusted_daily_velocity' => (float) $snapshot['adjusted_daily_velocity'],
            'days_until_stockout' => (float) $snapshot['days_until_stockout'],
            'estimated_lost_profit' => (float) $snapshot['estimated_lost_profit'],
            'confidence_score' => (float) $snapshot['confidence_score'],
            'data_freshness_minutes' => (int) $snapshot['data_freshness_minutes'],
            'current_stock' => (float) $snapshot['current_stock'],
            'unit_margin' => (float) $snapshot['unit_margin'],
            'lead_time_days' => (float) $snapshot['lead_time_days'],
            'sale_events_30d' => (int) $snapshot['sale_events_30d'],
            'payload' => pSQL(json_encode($snapshot)),
            'computed_at' => pSQL($snapshot['computed_at']),
        );

        $where = 'id_shop = ' . (int) $idShop
            . ' AND id_product = ' . (int) $variant['id_product']
            . ' AND id_product_attribute = ' . (int) $variant['id_product_attribute'];

        if (Db::getInstance()->getValue('SELECT id_metrixon_risk_snapshot FROM `' . _DB_PREFIX_ . 'metrixon_risk_snapshot` WHERE ' . $where)) {
            return Db::getInstance()->update('metrixon_risk_snapshot', $payload, $where);
        }

        return Db::getInstance()->insert('metrixon_risk_snapshot', $payload);
    }

    public function findOpenAlert($idShop, $idProduct, $idProductAttribute)
    {
        return Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'metrixon_risk_alert`'
            . ' WHERE id_shop = ' . (int) $idShop
            . ' AND id_product = ' . (int) $idProduct
            . ' AND id_product_attribute = ' . (int) $idProductAttribute
            . ' AND state IN ("active", "snoozed")'
            . ' ORDER BY date_upd DESC'
        );
    }

    public function findLatestAlert($idShop, $idProduct, $idProductAttribute)
    {
        return Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'metrixon_risk_alert`'
            . ' WHERE id_shop = ' . (int) $idShop
            . ' AND id_product = ' . (int) $idProduct
            . ' AND id_product_attribute = ' . (int) $idProductAttribute
            . ' ORDER BY date_add DESC'
        );
    }

    public function countActiveAlerts($idShop)
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'metrixon_risk_alert`'
            . ' WHERE id_shop = ' . (int) $idShop . ' AND state = "active"'
        );
    }

    public function getAlerts($idShop, $state = null, $limit = 100)
    {
        $where = 'id_shop = ' . (int) $idShop;
        if ($state) {
            $where .= ' AND state = "' . pSQL($state) . '"';
        }

        return Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'metrixon_risk_alert`'
            . ' WHERE ' . $where
            . ' ORDER BY FIELD(severity, "critical", "high"), date_upd DESC'
            . ' LIMIT ' . (int) $limit
        );
    }

    public function getAlert($idAlert, $idShop)
    {
        return Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'metrixon_risk_alert`'
            . ' WHERE id_metrixon_risk_alert = ' . (int) $idAlert
            . ' AND id_shop = ' . (int) $idShop
        );
    }

    public function createAlert($idShop, array $variant, array $decision)
    {
        $now = date('Y-m-d H:i:s');
        Db::getInstance()->insert('metrixon_risk_alert', array(
            'alert_uid' => pSQL(sha1($idShop . '-' . $variant['id_product'] . '-' . $variant['id_product_attribute'] . '-' . microtime(true))),
            'id_shop' => (int) $idShop,
            'id_product' => (int) $variant['id_product'],
            'id_product_attribute' => (int) $variant['id_product_attribute'],
            'sku' => pSQL($variant['sku']),
            'product_name' => pSQL($variant['product_name']),
            'variant_name' => pSQL($variant['variant_name']),
            'alert_type' => 'BESTSELLER_STOCKOUT_URGENT',
            'state' => self::STATE_ACTIVE,
            'severity' => pSQL($decision['severity']),
            'reason_codes' => pSQL(json_encode($decision['reason_codes'])),
            'compiled_truth' => pSQL(json_encode($decision['compiled_truth'])),
            'timeline' => pSQL(json_encode($decision['timeline'])),
            'action_recommendation' => pSQL(json_encode($decision['action_recommendation'])),
            'last_days_until_stockout' => (float) $decision['compiled_truth']['days_until_stockout'],
            'last_estimated_lost_profit' => (float) $decision['compiled_truth']['estimated_lost_profit'],
            'resolved_streak' => 0,
            'date_add' => $now,
            'date_upd' => $now,
        ));

        $idAlert = (int) Db::getInstance()->Insert_ID();
        $this->recordEvent($idShop, $idAlert, $this->variantKey($variant), 'alert_created', null, $decision);

        return $idAlert;
    }

    public function refreshAlert($idShop, $idAlert, array $variant, array $decision)
    {
        $updated = Db::getInstance()->update('metrixon_risk_alert', array(
            'state' => self::STATE_ACTIVE,
            'severity' => pSQL($decision['severity']),
            'reason_codes' => pSQL(json_encode($decision['reason_codes'])),
            'compiled_truth' => pSQL(json_encode($decision['compiled_truth'])),
            'timeline' => pSQL(json_encode($decision['timeline'])),
            'action_recommendation' => pSQL(json_encode($decision['action_recommendation'])),
            'last_days_until_stockout' => (float) $decision['compiled_truth']['days_until_stockout'],
            'last_estimated_lost_profit' => (float) $decision['compiled_truth']['estimated_lost_profit'],
            'snoozed_until' => null,
            'resolved_streak' => 0,
            'date_upd' => date('Y-m-d H:i:s'),
        ), 'id_metrixon_risk_alert = ' . (int) $idAlert);

        if ($updated) {
            $this->recordEvent($idShop, $idAlert, $this->variantKey($variant), 'alert_realerted', null, $decision);
        }

        return $updated;
    }

    public function setState($idShop, $idAlert, $state, $reason, array $extra = array())
    {
        $data = array(
            'state' => pSQL($state),
            'feedback_reason' => $reason ? pSQL($reason) : null,
            'date_upd' => date('Y-m-d H:i:s'),
        );

        if ($state === self::STATE_SNOOZED && isset($extra['snoozed_until'])) {
            $data['snoozed_until'] = pSQL($extra['snoozed_until']);
        }

        $updated = Db::getInstance()->update(
            'metrixon_risk_alert',
            $data,
            'id_metrixon_risk_alert = ' . (int) $idAlert . ' AND id_shop = ' . (int) $idShop
        );

        if ($updated) {
            $alert = $this->getAlert($idAlert, $idShop);
            $this->recordEvent($idShop, $idAlert, $alert ? $this->variantKey($alert) : null, 'alert_' . $state, $reason, $extra);
        }

        return $updated;
    }

    public function resolveNoLongerQualifying($idShop, array $qualifyingVariantKeys)
    {
        $alerts = $this->getAlerts($idShop, self::STATE_ACTIVE, 250);
        foreach ($alerts as $alert) {
            if (!isset($qualifyingVariantKeys[$this->variantKey($alert)])) {
                $this->setState($idShop, (int) $alert['id_metrixon_risk_alert'], self::STATE_RESOLVED, 'risk_no_longer_qualifies');
            }
        }
    }

    public function updateResolutionTracking($idShop, $idAlert, $resolvedStreak = null, $notBestsellerSince = null)
    {
        $data = array('date_upd' => date('Y-m-d H:i:s'));
        if ($resolvedStreak !== null) {
            $data['resolved_streak'] = (int) $resolvedStreak;
        }
        if ($notBestsellerSince !== null) {
            $data['not_bestseller_since'] = $notBestsellerSince ? pSQL($notBestsellerSince) : null;
        }

        return Db::getInstance()->update(
            'metrixon_risk_alert',
            $data,
            'id_metrixon_risk_alert = ' . (int) $idAlert . ' AND id_shop = ' . (int) $idShop
        );
    }

    public function wakeExpiredSnoozes($idShop)
    {
        $alerts = Db::getInstance()->executeS(
            'SELECT id_metrixon_risk_alert FROM `' . _DB_PREFIX_ . 'metrixon_risk_alert`'
            . ' WHERE id_shop = ' . (int) $idShop
            . ' AND state = "snoozed"'
            . ' AND snoozed_until IS NOT NULL'
            . ' AND snoozed_until <= "' . pSQL(date('Y-m-d H:i:s')) . '"'
        );

        foreach ($alerts as $alert) {
            $this->setState($idShop, (int) $alert['id_metrixon_risk_alert'], self::STATE_ACTIVE, 'snooze_expired');
        }
    }

    public function recordEvent($idShop, $idAlert, $variantKey, $eventType, $reason = null, array $payload = array())
    {
        return Db::getInstance()->insert('metrixon_risk_event', array(
            'id_shop' => (int) $idShop,
            'id_metrixon_risk_alert' => $idAlert ? (int) $idAlert : null,
            'variant_key' => $variantKey ? pSQL($variantKey) : null,
            'event_type' => pSQL($eventType),
            'reason' => $reason ? pSQL($reason) : null,
            'payload' => pSQL(json_encode($payload)),
            'date_add' => date('Y-m-d H:i:s'),
        ));
    }

    public function recordPolicyChange($idShop, $key, $oldValue, $newValue, $idEmployee)
    {
        return Db::getInstance()->insert('metrixon_risk_policy_audit', array(
            'id_shop' => (int) $idShop,
            'setting_key' => pSQL($key),
            'old_value' => $oldValue === null ? null : pSQL((string) $oldValue),
            'new_value' => $newValue === null ? null : pSQL((string) $newValue),
            'id_employee' => $idEmployee ? (int) $idEmployee : null,
            'date_add' => date('Y-m-d H:i:s'),
        ));
    }

    public function getEvents($idShop, $idAlert = null, $limit = 100)
    {
        $where = 'id_shop = ' . (int) $idShop;
        if ($idAlert) {
            $where .= ' AND id_metrixon_risk_alert = ' . (int) $idAlert;
        }

        return Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'metrixon_risk_event`'
            . ' WHERE ' . $where
            . ' ORDER BY date_add DESC'
            . ' LIMIT ' . (int) $limit
        );
    }

    public function variantKey(array $variant)
    {
        return (int) $variant['id_product'] . ':' . (int) $variant['id_product_attribute'];
    }

    public static function makeVariantKey($idProduct, $idProductAttribute)
    {
        return (int) $idProduct . ':' . (int) $idProductAttribute;
    }
}
