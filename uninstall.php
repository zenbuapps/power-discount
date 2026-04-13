<?php
declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// MVP policy: keep data by default to avoid destroying historical order records.
// Only remove transient-style options here. "Delete all data" lives in the settings page.
delete_option('power_discount_installed_at');
