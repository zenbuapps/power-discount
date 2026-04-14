# Power Discount

WooCommerce discount rules engine — Taiwan-first.

## Status

**Phase 4d (GUI Rule Builder)** — complete. **MVP polish.**

Rule editor now provides:
- Strategy-specific config forms for all 8 types (no more raw JSON)
- Filter row builder with WC enhanced-select for products / categories / tags
- Condition row builder with 13 condition types and type-specific fields
- AND / OR condition logic toggle
- Schedule, usage limit, priority, exclusive mode
- Cart label for customer-facing messaging
- No internal notes field (was a dev-only field)

All previous features remain: 8 strategies, 13 conditions, 6 filters, ShippingHooks, Reports, frontend shipping bar, price table shortcode.

Pending (post-MVP polish): live discount preview, drag-sort priority, CSV export on reports.

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
