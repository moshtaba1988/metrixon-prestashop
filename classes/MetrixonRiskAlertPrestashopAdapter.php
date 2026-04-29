<?php
/**
 * Maps PrestaShop catalog and order data into canonical risk inputs.
 *
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MetrixonRiskAlertPrestashopAdapter
{
    /**
     * @var Db
     */
    private $db;

    /**
     * @var Context
     */
    private $context;

    public function __construct(Context $context = null)
    {
        $this->db = Db::getInstance();
        $this->context = $context ?: Context::getContext();
    }

    public function getCapabilities()
    {
        return array(
            'supports_order_webhooks' => false,
            'supports_inventory_webhooks' => false,
            'supports_refund_events' => true,
            'supports_po_creation' => false,
            'supports_variant_cost' => true,
            'supports_realtime_inventory' => false,
            'sync_model' => 'polling',
            'freshness_sla_minutes' => 360,
            'fallbacks' => array(
                'purchase_order' => 'Use replenishment CTA to open the product stock page.',
                'lead_time' => 'Store default lead time is used unless customized later.',
                'margin' => 'Wholesale price is preferred; otherwise store default margin is used with a confidence penalty.',
            ),
        );
    }

    public function getStore($idShop)
    {
        $shop = new Shop((int) $idShop);
        $currency = new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT'));

        return array(
            'store_id' => (string) $idShop,
            'timezone' => Configuration::get('PS_TIMEZONE') ?: 'UTC',
            'currency_code' => $currency->iso_code ?: 'EUR',
            'shop_name' => $shop->name,
        );
    }

    public function getCatalogRiskInputs($idShop, array $policy)
    {
        $variants = $this->getVariants($idShop);
        if (!$variants) {
            return array();
        }

        $sales30 = $this->getSalesAggregates($idShop, 30);
        $sales7 = $this->getSalesAggregates($idShop, 7);
        $lastOrderAt = $this->getLastQualifyingOrderDate($idShop);
        $rankedKeys = $this->rankVariantKeys($sales30);
        $totalSellingVariants = count($rankedKeys);
        $bestsellerCutoff = $this->getBestsellerCutoff($totalSellingVariants, $policy);
        $now = date('Y-m-d H:i:s');
        $freshnessMinutes = $this->getFreshnessMinutes($lastOrderAt);
        $inputs = array();

        foreach ($variants as $variant) {
            $variantKey = $this->variantKey($variant['id_product'], $variant['id_product_attribute']);
            $thirtyDay = isset($sales30[$variantKey]) ? $sales30[$variantKey] : $this->emptySalesAggregate();
            $sevenDay = isset($sales7[$variantKey]) ? $sales7[$variantKey] : $this->emptySalesAggregate();
            $rank = isset($rankedKeys[$variantKey]) ? $rankedKeys[$variantKey] + 1 : null;
            $isBestseller = $rank !== null && $rank <= $bestsellerCutoff;
            $leadTime = (float) $policy['default_lead_time_days'];
            $margin = $this->resolveUnitMargin($variant, (float) $policy['default_margin_percent']);
            $dailyVelocity7d = (float) $sevenDay['quantity'] / 7;
            $dailyVelocity30d = (float) $thirtyDay['quantity'] / 30;
            $adjustedVelocity = $dailyVelocity7d;
            $daysUntilStockout = $adjustedVelocity > 0
                ? ((float) $variant['current_stock'] / $adjustedVelocity)
                : 999999;
            $estimatedLostProfit = max(0, $leadTime - $daysUntilStockout) * $adjustedVelocity * $margin['unit_margin'];
            $confidence = $this->calculateConfidence($thirtyDay, $freshnessMinutes, $margin['source']);

            $inputs[] = array(
                'store' => $this->getStore($idShop),
                'variant' => array(
                    'variant_id' => (string) $variantKey,
                    'sku' => $variant['sku'] ?: null,
                    'product_id' => (string) $variant['id_product'],
                    'product_name' => $variant['product_name'],
                    'variant_name' => $variant['variant_name'] ?: null,
                    'status' => $variant['active'] ? 'active' : 'inactive',
                    'id_product' => (int) $variant['id_product'],
                    'id_product_attribute' => (int) $variant['id_product_attribute'],
                ),
                'inventory_position' => array(
                    'variant_id' => (string) $variantKey,
                    'current_stock' => max(0, (float) $variant['current_stock']),
                    'captured_at' => $now,
                ),
                'supplier_profile' => array(
                    'variant_id' => (string) $variantKey,
                    'supplier_lead_time_days' => $leadTime,
                    'source' => 'store_default',
                ),
                'sales' => array(
                    'units_7d' => (float) $sevenDay['quantity'],
                    'units_30d' => (float) $thirtyDay['quantity'],
                    'sale_events_30d' => (int) $thirtyDay['events'],
                    'revenue_30d' => (float) $thirtyDay['revenue'],
                    'last_sale_at' => $thirtyDay['last_sale_at'],
                ),
                'ranking' => array(
                    'rank_30d' => $rank,
                    'bestseller_cutoff' => $bestsellerCutoff,
                    'total_selling_variants' => $totalSellingVariants,
                    'is_bestseller' => $isBestseller,
                ),
                'risk_snapshot' => array(
                    'variant_id' => (string) $variantKey,
                    'daily_velocity_7d' => $dailyVelocity7d,
                    'daily_velocity_30d' => $dailyVelocity30d,
                    'adjusted_daily_velocity' => $adjustedVelocity,
                    'days_until_stockout' => $daysUntilStockout,
                    'estimated_lost_profit' => $estimatedLostProfit,
                    'confidence_score' => $confidence,
                    'data_freshness_minutes' => $freshnessMinutes,
                    'computed_at' => $now,
                    'current_stock' => max(0, (float) $variant['current_stock']),
                    'unit_margin' => $margin['unit_margin'],
                    'margin_source' => $margin['source'],
                    'lead_time_days' => $leadTime,
                    'sale_events_30d' => (int) $thirtyDay['events'],
                ),
            );
        }

        return $inputs;
    }

    private function getVariants($idShop)
    {
        $idLang = (int) $this->context->language->id;
        $query = 'SELECT p.id_product, COALESCE(pa.id_product_attribute, 0) AS id_product_attribute,
                p.active, pl.name AS product_name,
                COALESCE(pa.reference, p.reference) AS sku,
                COALESCE(pa.wholesale_price, p.wholesale_price) AS wholesale_price,
                COALESCE(pa.price + p.price, p.price) AS price,
                sa.quantity AS current_stock,
                GROUP_CONCAT(DISTINCT al.name ORDER BY agl.position, al.name SEPARATOR " / ") AS variant_name
            FROM `' . _DB_PREFIX_ . 'product` p
            INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps
                ON ps.id_product = p.id_product AND ps.id_shop = ' . (int) $idShop . '
            INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                ON pl.id_product = p.id_product AND pl.id_shop = ' . (int) $idShop . ' AND pl.id_lang = ' . $idLang . '
            LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa
                ON pa.id_product = p.id_product
            LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac
                ON pac.id_product_attribute = pa.id_product_attribute
            LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` al
                ON al.id_attribute = pac.id_attribute AND al.id_lang = ' . $idLang . '
            LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a
                ON a.id_attribute = pac.id_attribute
            LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl
                ON agl.id_attribute_group = a.id_attribute_group AND agl.id_lang = ' . $idLang . '
            LEFT JOIN `' . _DB_PREFIX_ . 'stock_available` sa
                ON sa.id_product = p.id_product
                AND sa.id_product_attribute = COALESCE(pa.id_product_attribute, 0)
                AND sa.id_shop = ' . (int) $idShop . '
            WHERE ps.active = 1
            GROUP BY p.id_product, pa.id_product_attribute, p.active, pl.name, pa.reference, p.reference,
                pa.wholesale_price, p.wholesale_price, pa.price, p.price, sa.quantity';

        return $this->db->executeS($query);
    }

    private function getSalesAggregates($idShop, $days)
    {
        $query = 'SELECT od.product_id AS id_product,
                od.product_attribute_id AS id_product_attribute,
                SUM(od.product_quantity) AS quantity,
                COUNT(DISTINCT o.id_order) AS events,
                SUM(od.total_price_tax_excl) AS revenue,
                MAX(o.date_add) AS last_sale_at
            FROM `' . _DB_PREFIX_ . 'orders` o
            INNER JOIN `' . _DB_PREFIX_ . 'order_detail` od ON od.id_order = o.id_order
            INNER JOIN `' . _DB_PREFIX_ . 'order_state` os ON os.id_order_state = o.current_state
            WHERE o.id_shop = ' . (int) $idShop . '
                AND o.valid = 1
                AND os.logable = 1
                AND o.date_add >= DATE_SUB(NOW(), INTERVAL ' . (int) $days . ' DAY)
            GROUP BY od.product_id, od.product_attribute_id';

        $rows = $this->db->executeS($query);
        $aggregates = array();
        foreach ($rows as $row) {
            $aggregates[$this->variantKey($row['id_product'], $row['id_product_attribute'])] = array(
                'quantity' => (float) $row['quantity'],
                'events' => (int) $row['events'],
                'revenue' => (float) $row['revenue'],
                'last_sale_at' => $row['last_sale_at'],
            );
        }

        return $aggregates;
    }

    private function getLastQualifyingOrderDate($idShop)
    {
        return $this->db->getValue(
            'SELECT MAX(o.date_add)
            FROM `' . _DB_PREFIX_ . 'orders` o
            INNER JOIN `' . _DB_PREFIX_ . 'order_state` os ON os.id_order_state = o.current_state
            WHERE o.id_shop = ' . (int) $idShop . ' AND o.valid = 1 AND os.logable = 1'
        );
    }

    private function rankVariantKeys(array $sales30)
    {
        uasort($sales30, function ($left, $right) {
            if ((float) $left['quantity'] === (float) $right['quantity']) {
                return 0;
            }

            return ((float) $left['quantity'] > (float) $right['quantity']) ? -1 : 1;
        });

        return array_flip(array_keys(array_filter($sales30, function ($aggregate) {
            return (float) $aggregate['quantity'] > 0;
        })));
    }

    private function getBestsellerCutoff($totalSellingVariants, array $policy)
    {
        if ((int) $totalSellingVariants === 0) {
            return 0;
        }

        if ($policy['bestseller_mode'] === MetrixonRiskConfig::BESTSELLER_MODE_TOP_N) {
            return min((int) $policy['bestseller_top_n'], (int) $totalSellingVariants);
        }

        $percentileCutoff = (int) ceil($totalSellingVariants * ((float) $policy['bestseller_percentile'] / 100));

        return max(1, min($totalSellingVariants, max($percentileCutoff, (int) $policy['bestseller_top_n'])));
    }

    private function getFreshnessMinutes($lastOrderAt)
    {
        if (!$lastOrderAt) {
            return 0;
        }

        return max(0, (int) floor((time() - strtotime($lastOrderAt)) / 60));
    }

    private function resolveUnitMargin(array $variant, $defaultMarginPercent)
    {
        $price = max(0, (float) $variant['price']);
        $wholesalePrice = max(0, (float) $variant['wholesale_price']);
        if ($price > 0 && $wholesalePrice > 0 && $price > $wholesalePrice) {
            return array(
                'unit_margin' => $price - $wholesalePrice,
                'source' => 'variant_cost',
            );
        }

        return array(
            'unit_margin' => $price * ($defaultMarginPercent / 100),
            'source' => 'store_default',
        );
    }

    private function calculateConfidence(array $sales30, $freshnessMinutes, $marginSource)
    {
        $confidence = 0.95;
        if ((int) $sales30['events'] < 5) {
            $confidence -= 0.20;
        }
        if ((int) $freshnessMinutes > 360) {
            $confidence -= 0.20;
        }
        if ($marginSource === 'store_default') {
            $confidence -= 0.10;
        }

        return max(0, min(1, $confidence));
    }

    private function emptySalesAggregate()
    {
        return array(
            'quantity' => 0,
            'events' => 0,
            'revenue' => 0,
            'last_sale_at' => null,
        );
    }

    private function variantKey($idProduct, $idProductAttribute)
    {
        return (int) $idProduct . ':' . (int) $idProductAttribute;
    }
}
