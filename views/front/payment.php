<?php

if (!defined('ABSPATH')) {
    exit;
}

/** @var string $paywall_iframe */
/** @var array $paywall_iframe_allowed_html */
/** @var bool $render_init_script */
/** @var array $paywall_options */
?>
<div id="comfino-iframe-container"><?php echo wp_kses($paywall_iframe, $paywall_iframe_allowed_html); ?></div>
<?php if ($render_init_script): ?>
<input id="comfino-loan-amount" name="comfino_loan_amount" type="hidden" />
<input id="comfino-loan-type" name="comfino_loan_type" type="hidden" />
<input id="comfino-loan-term" name="comfino_loan_term" type="hidden" />
<script>ComfinoPaywall.initIframe();</script>
<?php endif; ?>
