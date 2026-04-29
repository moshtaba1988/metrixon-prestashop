<?php
/**
 * Deterministic BESTSELLER_STOCKOUT_URGENT policy.
 *
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MetrixonRiskAlertPolicy
{
    const ALERT_TYPE = 'BESTSELLER_STOCKOUT_URGENT';

    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function evaluateVariant(array $variant, $rank, $catalogSize, $computedAt)
    {
        $snapshot = $this->buildSnapshot($variant, $computedAt);
        $isBestseller = $this->isBestseller((int) $rank, (int) $catalogSize);
        $reasonCodes = array();
        $failures = array();

        if ($isBestseller) {
            $reasonCodes[] = 'BESTSELLER';
        } else {
            $failures[] = 'NOT_BESTSELLER';
        }

        if ($snapshot['days_until_stockout'] <= $this->config['urgency_threshold_days']) {
            $reasonCodes[] = 'URGENT_STOCKOUT_WINDOW';
        } else {
            $failures[] = 'OUTSIDE_URGENCY_WINDOW';
        }

        if ($snapshot['days_until_stockout'] < $snapshot['lead_time_days']) {
            $reasonCodes[] = 'LEADTIME_GAP';
        } else {
            $failures[] = 'NO_LEADTIME_GAP';
        }

        if ($snapshot['estimated_lost_profit'] >= $this->config['materiality_floor']) {
            $reasonCodes[] = 'MATERIAL_PROFIT_RISK';
        } else {
            $failures[] = 'BELOW_MATERIALITY_FLOOR';
        }

        if ($snapshot['confidence_score'] >= $this->config['confidence_minimum']) {
            $reasonCodes[] = 'HIGH_CONFIDENCE';
        } else {
            $failures[] = 'LOW_CONFIDENCE';
        }

        if ($snapshot['data_freshness_minutes'] <= $this->config['freshness_max_minutes']) {
            $reasonCodes[] = 'FRESH_DATA';
        } else {
            $failures[] = 'STALE_DATA';
        }

        if ($snapshot['sale_events_30d'] >= $this->config['min_sale_events']) {
            $reasonCodes[] = 'RECENT_SALES_EVIDENCE';
        } else {
            $failures[] = 'INSUFFICIENT_SALES_EVIDENCE';
        }

        $qualifies = empty($failures);
        if ($qualifies) {
            $reasonCodes[] = 'ACTIONABLE_REPLENISHMENT';
        }

        $variantKey = isset($variant['variant_key'])
            ? $variant['variant_key']
            : (isset($variant['variant_id'])
                ? $variant['variant_id']
                : ((int) $variant['id_product'] . ':' . (int) $variant['id_product_attribute']));

        return array(
            'qualifies' => $qualifies,
            'failures' => $failures,
            'variant_key' => $variantKey,
            'id_product' => (int) $variant['id_product'],
            'id_product_attribute' => (int) $variant['id_product_attribute'],
            'sku' => $variant['sku'],
            'product_name' => $variant['product_name'],
            'variant_name' => $variant['variant_name'],
            'severity' => $snapshot['days_until_stockout'] <= 2 ? 'critical' : 'high',
            'reason_codes' => $reasonCodes,
            'snapshot' => $snapshot,
            'compiled_truth' => $this->buildCompiledTruth($variant, $snapshot, $rank, $catalogSize),
            'timeline' => $this->buildTimeline($variant, $snapshot, $computedAt),
            'action' => $this->buildAction($variant, $snapshot),
        );
    }

    public function evaluateInput(array $input)
    {
        $variant = $input['variant'] + array(
            'current_stock' => $input['inventory_position']['current_stock'],
            'units_7d' => $input['sales']['units_7d'],
            'units_30d' => $input['sales']['units_30d'],
            'sale_events_30d' => $input['sales']['sale_events_30d'],
            'last_sale_at' => $input['sales']['last_sale_at'],
            'inventory_captured_at' => $input['inventory_position']['captured_at'],
            'unit_price' => $input['sales']['units_30d'] > 0
                ? ((float) $input['sales']['revenue_30d'] / (float) $input['sales']['units_30d'])
                : 0,
            'wholesale_price' => 0,
            'lead_time_days' => $input['supplier_profile']['supplier_lead_time_days'],
            'lead_time_source' => $input['supplier_profile']['source'],
            'data_freshness_minutes' => $input['risk_snapshot']['data_freshness_minutes'],
            'margin_source' => $input['risk_snapshot']['margin_source'],
        );

        $variant['unit_price'] = $variant['unit_price'] > 0
            ? $variant['unit_price']
            : ($input['risk_snapshot']['unit_margin'] / max(0.01, ((float) $this->config['default_margin_percent'] / 100)));
        if ($input['risk_snapshot']['margin_source'] === 'variant_cost') {
            $variant['wholesale_price'] = max(0, $variant['unit_price'] - $input['risk_snapshot']['unit_margin']);
        }
        if (!isset($variant['variant_key'])) {
            $variant['variant_key'] = isset($variant['variant_id'])
                ? $variant['variant_id']
                : ((int) $variant['id_product'] . ':' . (int) $variant['id_product_attribute']);
        }

        $decision = $this->evaluateVariant(
            $variant,
            (int) $input['ranking']['rank_30d'],
            (int) $input['ranking']['total_selling_variants'],
            $input['risk_snapshot']['computed_at']
        );
        $decision['variant'] = $input['variant'];

        return $decision;
    }

    public function getMaxActiveAlerts()
    {
        return (int) $this->config['max_active_alerts'];
    }

    public function canEmit(array $decision, $existingAlert, $activeAlertCount)
    {
        if (!$decision['qualifies']) {
            return false;
        }

        if ($existingAlert) {
            if ($existingAlert['state'] === 'snoozed'
                && !empty($existingAlert['snoozed_until'])
                && strtotime($existingAlert['snoozed_until']) > time()) {
                return false;
            }

            $cooldownSeconds = (int) $this->config['cooldown_hours'] * 3600;
            $inCooldown = strtotime($existingAlert['date_upd']) > time() - $cooldownSeconds;
            $daysDropped = ((float) $existingAlert['last_days_until_stockout'] - (float) $decision['snapshot']['days_until_stockout']) >= 1;
            $profitRose = (float) $existingAlert['last_estimated_lost_profit'] > 0
                && (float) $decision['snapshot']['estimated_lost_profit'] >= ((float) $existingAlert['last_estimated_lost_profit'] * 1.2);

            return !$inCooldown || $daysDropped || $profitRose;
        }

        return (int) $activeAlertCount < (int) $this->config['max_active_alerts'];
    }

    private function buildSnapshot(array $variant, $computedAt)
    {
        $velocity7d = (float) $variant['units_7d'] / 7;
        $velocity30d = (float) $variant['units_30d'] / 30;
        $adjustedVelocity = $velocity7d;
        if ($velocity7d <= 0 && $velocity30d > 0) {
            $adjustedVelocity = $velocity30d;
        }

        $daysUntilStockout = $adjustedVelocity > 0 ? (float) $variant['current_stock'] / $adjustedVelocity : 9999;
        $leadTime = isset($variant['lead_time_days']) && (float) $variant['lead_time_days'] > 0
            ? (float) $variant['lead_time_days']
            : (float) $this->config['default_lead_time_days'];
        $unitMargin = $this->resolveUnitMargin($variant);
        $lostProfit = max(0, $leadTime - $daysUntilStockout) * $adjustedVelocity * $unitMargin;
        $freshnessMinutes = isset($variant['data_freshness_minutes']) ? (int) $variant['data_freshness_minutes'] : 0;
        $confidence = $this->confidenceScore($variant, $freshnessMinutes, $adjustedVelocity);

        return array(
            'daily_velocity_7d' => round($velocity7d, 6),
            'daily_velocity_30d' => round($velocity30d, 6),
            'adjusted_daily_velocity' => round($adjustedVelocity, 6),
            'days_until_stockout' => round($daysUntilStockout, 2),
            'estimated_lost_profit' => round($lostProfit, 2),
            'confidence_score' => round($confidence, 4),
            'confidence_label' => $confidence >= 0.85 ? 'high' : ($confidence >= 0.70 ? 'medium' : 'low'),
            'data_freshness_minutes' => $freshnessMinutes,
            'current_stock' => (float) $variant['current_stock'],
            'unit_margin' => round($unitMargin, 2),
            'margin_source' => $variant['margin_source'],
            'lead_time_days' => $leadTime,
            'lead_time_source' => $variant['lead_time_source'],
            'sale_events_30d' => (int) $variant['sale_events_30d'],
            'computed_at' => $computedAt,
        );
    }

    private function resolveUnitMargin(array &$variant)
    {
        $price = (float) $variant['unit_price'];
        $cost = isset($variant['wholesale_price']) ? (float) $variant['wholesale_price'] : 0;

        if ($price > 0 && $cost > 0 && $cost < $price) {
            $variant['margin_source'] = 'variant_cost';
            return $price - $cost;
        }

        $variant['margin_source'] = 'store_default_margin_config';
        return $price * ((float) $this->config['default_margin_percent'] / 100);
    }

    private function confidenceScore(array $variant, $freshnessMinutes, $adjustedVelocity)
    {
        $score = 1.0;
        if ($freshnessMinutes > $this->config['freshness_max_minutes']) {
            $score -= 0.35;
        }
        if ((int) $variant['sale_events_30d'] < $this->config['min_sale_events']) {
            $score -= 0.25;
        }
        if ($adjustedVelocity <= 0) {
            $score -= 0.5;
        }
        if (isset($variant['margin_source']) && $variant['margin_source'] === 'store_default_margin_config') {
            $score -= 0.10;
        }
        if ($variant['lead_time_source'] === 'default') {
            $score -= 0.05;
        }

        return max(0, min(1, $score));
    }

    private function isBestseller($rank, $catalogSize)
    {
        if ($catalogSize <= 0 || $rank <= 0) {
            return false;
        }

        $topN = max(1, (int) $this->config['top_n']);
        if ($this->config['bestseller_mode'] === MetrixonRiskConfig::BESTSELLER_MODE_TOP_N) {
            return $rank <= $topN;
        }

        $percentileLimit = max(1, (int) ceil($catalogSize * ((float) $this->config['bestseller_percentile'] / 100)));

        return $rank <= $percentileLimit || $rank <= $topN;
    }

    private function buildCompiledTruth(array $variant, array $snapshot, $rank, $catalogSize)
    {
        return array(
            'sku' => $variant['sku'],
            'product_name' => $variant['product_name'],
            'variant_name' => $variant['variant_name'],
            'bestseller_rank' => (int) $rank,
            'catalog_size' => (int) $catalogSize,
            'current_stock' => $snapshot['current_stock'],
            'daily_velocity_7d' => $snapshot['daily_velocity_7d'],
            'daily_velocity_30d' => $snapshot['daily_velocity_30d'],
            'adjusted_daily_velocity' => $snapshot['adjusted_daily_velocity'],
            'days_until_stockout' => $snapshot['days_until_stockout'],
            'lead_time_days' => $snapshot['lead_time_days'],
            'estimated_lost_profit' => $snapshot['estimated_lost_profit'],
            'confidence_score' => $snapshot['confidence_score'],
            'confidence_label' => $snapshot['confidence_label'],
            'freshness_stamp' => $snapshot['data_freshness_minutes'] . ' minutes old',
            'margin_source' => $snapshot['margin_source'],
            'lead_time_source' => $snapshot['lead_time_source'],
            'policy' => array(
                'urgency_threshold_days' => $this->config['urgency_threshold_days'],
                'materiality_floor' => $this->config['materiality_floor'],
                'confidence_minimum' => $this->config['confidence_minimum'],
                'freshness_max_minutes' => $this->config['freshness_max_minutes'],
            ),
        );
    }

    private function buildTimeline(array $variant, array $snapshot, $computedAt)
    {
        return array(
            array(
                'time' => $computedAt,
                'event' => 'Risk evaluation completed',
                'value' => 'Stockout in ' . $snapshot['days_until_stockout'] . ' days',
            ),
            array(
                'time' => $computedAt,
                'event' => 'Threshold crossed',
                'value' => 'Days until stockout <= ' . $this->config['urgency_threshold_days']
                    . ' and below lead time of ' . $snapshot['lead_time_days'] . ' days',
            ),
            array(
                'time' => $variant['last_sale_at'] ?: $computedAt,
                'event' => 'Velocity observed',
                'value' => $snapshot['daily_velocity_7d'] . ' units/day over 7 days',
            ),
            array(
                'time' => $variant['inventory_captured_at'] ?: $computedAt,
                'event' => 'Inventory synced',
                'value' => $snapshot['current_stock'] . ' units available',
            ),
        );
    }

    private function buildAction(array $variant, array $snapshot)
    {
        $recommendedQty = (int) ceil(max(0, $snapshot['lead_time_days'] + 5) * $snapshot['adjusted_daily_velocity'] - $snapshot['current_stock']);

        return array(
            'primary_cta' => 'Replenish now',
            'secondary_cta' => 'Adjust lead time/settings',
            'deadline' => 'before ' . max(0, $snapshot['days_until_stockout']) . ' days',
            'recommended_quantity' => max(1, $recommendedQty),
            'rationale' => 'Projected stockout occurs before supplier lead time and may risk '
                . $snapshot['estimated_lost_profit'] . ' in lost profit.',
        );
    }
}
