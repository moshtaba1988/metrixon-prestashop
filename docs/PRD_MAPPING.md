# PRD Mapping

This module implements the pilot `BESTSELLER_STOCKOUT_URGENT` class from the
Version 3 Risk Alert System PRD.

## Canonical entities

| PRD entity | PrestaShop source |
| --- | --- |
| Store | Shop context, shop timezone, configured currency |
| Variant | `product`, `product_attribute`, `stock_available` |
| InventoryPosition | `stock_available.quantity` at evaluation time |
| SaleEvent | Paid/accepted `orders` and `order_detail` rows in 7/30 day windows |
| SupplierProfile | Merchant default lead time configuration |
| RiskSnapshot | `metrixon_risk_snapshot` |
| RiskAlert | `metrixon_risk_alert` |

## Decision policy

- Best-seller gate: percentile or top N setting, defaulting to top 20% with top
  10 support for small catalogs.
- Urgency gate: `days_until_stockout <= 5` and below configured lead time.
- Materiality gate: `estimated_lost_profit >= 150` by default.
- Confidence gate: score at least `0.70`, data at most `360` minutes old, and at
  least 5 sale events in the 30-day lookback.
- Suppression: one active/snoozed alert per variant, 24h cooldown, re-alert only
  when days-to-stockout deteriorates by at least one day or lost profit rises at
  least 20%, and a maximum of 5 active alerts.

## Explainability

Every created or refreshed alert stores:

- `reason_codes`
- `compiled_truth`
- newest-first `timeline`
- `action_recommendation`
- deterministic numeric inputs and formulas

## Lifecycle and KPI events

The module records events in `metrixon_risk_event` for alert creation,
suppression, re-alerting, snooze, dismissal, resolution, and policy changes.
These events support pilot trust metrics such as action rate, dismissals as
noise, and stale alert incidence.

## Adapter capability declaration

The PrestaShop adapter declares the available capabilities in the admin settings
screen:

- order webhooks: no
- inventory webhooks: no
- refund events: no
- purchase order creation: no
- variant cost: yes, via PrestaShop wholesale price when present
- realtime inventory: no

The module uses polling/cron and documented defaults for unsupported
capabilities rather than failing silently.
