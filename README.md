# Power Discount

WooCommerce discount rules engine — Taiwan-first.

## Status

**Phase 3 (Taiwan Strategies)** — complete.

- All 8 strategies now registered: `simple`, `bulk`, `cart`, `set`, `buy_x_get_y`, `nth_item`, `cross_category`, `free_shipping`
- Taiwan-first features:
  - **Buy X Get Y** (same / specific / cheapest_in_cart targets)
  - **第 N 件 X 折** (NthItemStrategy with recursive cycles)
  - **紅配綠** (CrossCategoryStrategy with multi-group bundles)
  - **免運** (FreeShipping, shipping-scope sentinel — real shipping manipulation lands in Phase 4)

Still pending: remaining 11 conditions + 4 filters (Phase 4), Admin UI (Phase 4), Frontend (Phase 4), real ShippingHooks (Phase 4).

## Requirements

- PHP 7.4+
- WordPress 6.0+
- WooCommerce 7.0+ (HPOS compatible)

## Development

```bash
composer install
vendor/bin/phpunit
```

## Architecture

See `docs/superpowers/specs/2026-04-14-power-discount-design.md`.

## License

GPL-2.0-or-later
