<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap pd-addon-activation">
    <h1 class="wp-heading-inline"><?php esc_html_e('加價購', 'power-discount'); ?></h1>
    <hr class="wp-header-end">

    <div class="pd-activation-card">
        <div class="pd-activation-icon">🛍️</div>
        <h2><?php esc_html_e('啟用加價購功能', 'power-discount'); ?></h2>
        <p class="pd-activation-lede">
            <?php esc_html_e('讓顧客在購買特定商品時，以特價加購其他商品。例如買咖啡豆特價 $30 加購濾紙。', 'power-discount'); ?>
        </p>
        <ul class="pd-activation-features">
            <li>✓ <?php esc_html_e('商品頁面自動顯示加價購專區', 'power-discount'); ?></li>
            <li>✓ <?php esc_html_e('雙向設定：規則管理頁與商品編輯頁互通', 'power-discount'); ?></li>
            <li>✓ <?php esc_html_e('每個加價購商品可自訂特價', 'power-discount'); ?></li>
            <li>✓ <?php esc_html_e('可選擇將加價購商品排除於其他折扣規則之外', 'power-discount'); ?></li>
        </ul>
        <p>
            <a href="<?php echo esc_url($activateUrl); ?>" class="button button-primary button-large">
                <?php esc_html_e('啟用加價購功能', 'power-discount'); ?>
            </a>
        </p>
    </div>
</div>
