<?php

if (!defined('ABSPATH')) {
    exit;
}

/** @var bool $render_init_script */
/** @var string $paywall_url */
/** @var array $paywall_options */
?>
<div id="comfino-iframe-container"></div>
<?php if ($render_init_script): ?>
<input id="comfino-loan-amount" name="comfino_loan_amount" type="hidden" />
<input id="comfino-loan-type" name="comfino_loan_type" type="hidden" />
<input id="comfino-loan-term" name="comfino_loan_term" type="hidden" />
<script data-cmp-ab="2">
    if (typeof window.ComfinoPaywallData === 'undefined') {
        window.ComfinoPaywallData = {
            paywallUrl: '<?php echo esc_js(esc_url_raw($paywall_url)); ?>',
            paywallOptions: <?php echo wp_json_encode($paywall_options); ?>
        };
    }
</script>
<?php endif; ?>
