# Phase 4a Manual Verification

## Setup

Activate `power-discount` on a real WP+WC staging site. Ensure at least one product has categories, tags, and variant attributes.

## Conditions

Create a rule requiring LINE Pay payment method + ≥ NT$500 subtotal:

```sql
INSERT INTO wp_pd_rules (title, type, status, priority, config, filters, conditions, created_at, updated_at)
VALUES (
  'LINE Pay -3%',
  'cart',
  1, 10,
  '{"method":"percentage","value":3}',
  '{}',
  '{"logic":"and","items":[{"type":"cart_subtotal","operator":">=","value":500},{"type":"payment_method","methods":["ecpay_linepay"]}]}',
  NOW(), NOW()
);
```

Verify:
- [ ] Cart subtotal < NT$500 → no discount even if LINE Pay selected
- [ ] Cart ≥ NT$500 with COD → no discount
- [ ] Cart ≥ NT$500 with LINE Pay → 3% cart discount applied

## Filters

Rule using tag filter:

```sql
INSERT INTO wp_pd_rules (title, type, status, priority, config, filters, conditions, created_at, updated_at)
VALUES (
  'New arrivals 10%',
  'simple',
  1, 10,
  '{"method":"percentage","value":10}',
  '{"items":[{"type":"tags","method":"in","ids":[NEW_ARRIVAL_TAG_ID]}]}',
  '{}',
  NOW(), NOW()
);
```

Verify:
- [ ] Items tagged "new arrival" get 10% off
- [ ] Other items unaffected

## Free Shipping (ShippingHooks)

Rule removing shipping when subtotal ≥ NT$1000:

```sql
INSERT INTO wp_pd_rules (title, type, status, priority, config, filters, conditions, created_at, updated_at)
VALUES (
  '滿千免運',
  'free_shipping',
  1, 10,
  '{"method":"remove_shipping"}',
  '{}',
  '{"logic":"and","items":[{"type":"cart_subtotal","operator":">=","value":1000}]}',
  NOW(), NOW()
);
```

Verify:
- [ ] Cart below NT$1000 → normal shipping cost shown
- [ ] Cart at NT$1000+ → all shipping options show NT$0
- [ ] Place order → order shipping total is 0; `wp_pd_order_discounts` has a `scope='shipping'` entry

## Known Gaps → Phase 4b/4c

- Admin UI not yet built (all rules via SQL)
- No REST API
- No frontend price table / shipping bar / saved label
- Reports page not built
