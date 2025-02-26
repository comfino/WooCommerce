<?php

if (!defined('ABSPATH')) {
    exit;
}

/** @var bool $render_init_script */
/** @var string $paywall_url */
/** @var array $paywall_options */
?>
<div id="comfino-iframe-container" class="comfino-iframe-container"></div>
<?php if ($render_init_script): ?>
<input id="comfino-loan-amount" name="comfino_loan_amount" type="hidden" />
<input id="comfino-loan-type" name="comfino_loan_type" type="hidden" />
<input id="comfino-loan-term" name="comfino_loan_term" type="hidden" />
<input id="comfino-price-modifier" name="comfino_price_modifier" type="hidden" />
<script data-cmp-ab="2">window.ComfinoPaywallData = { paywallUrl: '<?php echo esc_js(esc_url_raw($paywall_url)); ?>', paywallOptions: <?php echo wp_json_encode($paywall_options); ?> }; if (typeof ComfinoPaywallInit === 'object') ComfinoPaywallInit.init();</script>
<?php endif; ?>
