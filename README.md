# Metrixon PrestaShop Risk Alert System

PrestaShop module implementing the Version 3 PRD for trust-first bestseller stockout alerting.

The module evaluates the pilot alert class `BESTSELLER_STOCKOUT_URGENT` using deterministic stock, sales, lead-time, materiality, confidence, freshness, suppression, and re-alert rules. It includes a Back Office alert list/detail UX, configurable policy guardrails, audit/KPI event persistence, and a token-protected cron endpoint.

## Install

1. Copy this repository into `modules/metrixonriskalert`.
2. In the PrestaShop Back Office, install **Metrixon Risk Alert System** from Module Manager.
3. Configure defaults under **Catalog > Risk Alert Settings**.
4. Schedule the cron URL displayed in settings:

   ```bash
   curl "https://example.com/module/metrixonriskalert/cron?token=<token>"
   ```

## Implemented PRD scope

- Canonical adapter mapping for store, variants, inventory positions, sale events, supplier profile defaults, risk snapshots, and risk alerts.
- Bestseller eligibility using either top percentile or top-N guardrail.
- Urgency, materiality, confidence, freshness, and recent sales evidence gates.
- Suppression with cooldown, deterioration-only re-alert, snooze, dismiss, resolve, and max active alert limits.
- Compiled truth, timeline, reason codes, action recommendation, and deterministic provenance for every active alert.
- KPI and audit events for lifecycle transitions and policy changes.
- Health/doctor checks for stale snapshots, configuration risk, active stale alerts, and adapter capability declarations.

## PrestaShop data mapping

- Variants: `product` + `product_attribute`, only active products.
- Inventory: `StockAvailable`.
- Sales: paid/valid orders in the previous 30 days via `orders`, `order_detail`, and `order_state`.
- Lead time: module store-level default in v1.
- Margin: variant/product wholesale price when available, otherwise module default margin percentage.

## Capability flags

This adapter declares:

- `supports_order_webhooks`: false
- `supports_inventory_webhooks`: false
- `supports_refund_events`: false
- `supports_po_creation`: false
- `supports_variant_cost`: true
- `supports_realtime_inventory`: false

The module therefore uses polling via cron, default lead time, and default margin fallback with a confidence penalty when wholesale price is unavailable, as required by the PRD fallback guidance.
