# Power Discount

WooCommerce discount rules engine — Taiwan-first.

## Status

**Phase 4a (Conditions + Filters + ShippingHooks)** — complete.

- 13 conditions available: `cart_subtotal`, `cart_quantity`, `cart_line_items`, `date_range`, `day_of_week`, `time_of_day`, `user_role`, `user_logged_in`, `payment_method`, `shipping_method`, `first_order`, `total_spent`, `birthday_month`
- 6 filters available: `all_products`, `products`, `categories`, `tags`, `attributes`, `on_sale`
- ShippingHooks consumes `shippingResults()` to modify WC package rates in real time
- CartContextBuilder populates tagIds, attributes, onSale from WC products

Still pending (Phase 4b/4c): Admin UI (React + WP_List_Table), REST API, Frontend (price table, shipping bar, saved label), Reports.

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
