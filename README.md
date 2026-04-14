# Power Discount

WooCommerce discount rules engine — Taiwan-first.

## Status

**Phase 4b (PHP Admin UI)** — complete.

- Admin menu under `WooCommerce → Power Discount`
- Rule list page (WP_List_Table): edit / duplicate / delete / AJAX status toggle
- Rule edit page (PHP form): title, type, status, priority, exclusive, schedule, usage limit, label, notes + JSON textareas for `config`, `filters`, `conditions`
- `RuleFormMapper` validates JSON and field requirements (unit-tested)
- Admin notices via transient queue

Pending: React rule builder (Phase 4d, optional), Frontend price table / shipping bar / saved label (Phase 4c), Reports page (Phase 4c).

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
