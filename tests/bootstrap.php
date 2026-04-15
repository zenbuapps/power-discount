<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Minimal WP i18n stubs so domain/admin classes that call __() can be
// unit-tested without loading WordPress.
if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}
if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}
if (!function_exists('esc_attr__')) {
    function esc_attr__(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}
