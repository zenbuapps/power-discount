<?php
declare(strict_types=1);

namespace PowerDiscount\I18n;

final class Loader
{
    public function register(): void
    {
        add_action('init', [$this, 'loadTextDomain']);
    }

    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'power-discount',
            false,
            dirname(POWER_DISCOUNT_BASENAME) . '/languages'
        );
    }
}
