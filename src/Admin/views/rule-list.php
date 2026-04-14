<?php
/** @var \PowerDiscount\Admin\RulesListTable $table */
/** @var string $newUrl */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap pd-rules-list">
    <h1 class="wp-heading-inline"><?php esc_html_e('Power Discount Rules', 'power-discount'); ?></h1>
    <a href="<?php echo esc_url($newUrl); ?>" class="page-title-action"><?php esc_html_e('Add New', 'power-discount'); ?></a>
    <hr class="wp-header-end">

    <form method="get">
        <input type="hidden" name="page" value="power-discount">
        <?php $table->display(); ?>
    </form>
</div>
