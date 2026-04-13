# Phase 3 Manual Verification

Strategies are unit-tested exhaustively, but end-to-end WC integration with the new rule types should be verified on a staging site before shipping to production clients.

## Setup

Activate `power-discount`. Schema v1 tables must exist.

Insert one rule per Taiwan strategy type using SQL like the examples below. Replace `{CAT}`, `{CAT_TOP}`, `{CAT_BOTTOM}`, `{PRODUCT_ID}` with real IDs.

## BuyXGetY — Buy 2 Get 1 Cheapest Free

```sql
INSERT INTO wp_pd_rules (title, type, status, priority, config, filters, conditions, created_at, updated_at)
VALUES (
  '買 2 送 1 最便宜',
  'buy_x_get_y',
  1,
  10,
  '{"trigger":{"source":"filter","qty":2},"reward":{"target":"cheapest_in_cart","qty":1,"method":"free","value":0},"recursive":true}',
  '{"items":[{"type":"categories","method":"in","ids":[{CAT}]}]}',
  '{}',
  NOW(),
  NOW()
);
```

Verify:
- [ ] Add 3 qualifying items to cart → cheapest should get 100% discount
- [ ] Add 6 qualifying items → 2 bundles, 2 cheapest free
- [ ] Single item → no discount

## NthItem — 第二件 6 折

```sql
INSERT INTO wp_pd_rules (title, type, status, priority, config, filters, conditions, created_at, updated_at)
VALUES (
  '第二件 6 折',
  'nth_item',
  1,
  10,
  '{"tiers":[{"nth":1,"method":"percentage","value":0},{"nth":2,"method":"percentage","value":40}],"sort_by":"price_desc","recursive":true}',
  '{"items":[{"type":"categories","method":"in","ids":[{CAT}]}]}',
  '{}',
  NOW(),
  NOW()
);
```

Verify:
- [ ] 2 items → first full price, second 60% of price shown
- [ ] 4 items → items 1,3 full price; items 2,4 at 60% (recursive)

## CrossCategory — 紅配綠上衣+褲子 8 折

```sql
INSERT INTO wp_pd_rules (title, type, status, priority, config, filters, conditions, created_at, updated_at)
VALUES (
  '紅配綠',
  'cross_category',
  1,
  10,
  '{"groups":[{"name":"上衣","filter":{"type":"categories","value":[{CAT_TOP}]},"min_qty":1},{"name":"褲子","filter":{"type":"categories","value":[{CAT_BOTTOM}]},"min_qty":1}],"reward":{"method":"percentage","value":20},"repeat":true}',
  '{}',
  '{}',
  NOW(),
  NOW()
);
```

Verify:
- [ ] 1 top + 1 pants → bundle total × 20% discount
- [ ] 3 tops + 3 pants → 3 bundles × 20% each
- [ ] 2 tops + 0 pants → no discount (group B unfulfilled)

## FreeShipping

```sql
INSERT INTO wp_pd_rules (title, type, status, priority, config, filters, conditions, created_at, updated_at)
VALUES (
  '滿 $1000 免運',
  'free_shipping',
  1,
  10,
  '{"method":"remove_shipping"}',
  '{}',
  '{"logic":"and","items":[{"type":"cart_subtotal","operator":">=","value":1000}]}',
  NOW(),
  NOW()
);
```

Verify:
- [ ] Rule hits above $1000 → `wp_pd_order_discounts` shows `scope='shipping'` entry
- [ ] Actual shipping is NOT removed yet — Phase 4 ShippingHooks will consume the sentinel result
- [ ] Amount logged is `1.0` (sentinel)

## Known Gaps → Phase 4

- FreeShipping sentinel not yet consumed by a real ShippingHooks hook
- CrossCategory inline `filter.value` uses a minimal format that doesn't go through FilterRegistry; Phase 4 will unify
- BuyXGetY doesn't yet support `cheapest_from_filter` reward target
- Still only 2 conditions + 2 filters available system-wide
