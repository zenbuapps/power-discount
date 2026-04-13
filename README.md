# Power Discount

WooCommerce discount rules engine — Taiwan-first.

## Status

**Phase 1 (Foundation + Core Strategies)** — in progress.

- Schema v1 for `wp_pd_rules` and `wp_pd_order_discounts`
- Domain value objects (`Rule`, `CartContext`, `CartItem`, `DiscountResult`)
- 4 core strategies: Simple / Bulk / Cart / Set
- Full PHPUnit coverage for domain + strategies

Not yet wired to WooCommerce cart hooks (Phase 2).

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
