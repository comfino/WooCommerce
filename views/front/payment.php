<?php

if (!defined('ABSPATH')) {
    exit;
}

/** @var string $paywall_iframe */
/** @var array $paywall_iframe_allowed_html */
/** @var bool $render_init_script */
?>
<div id="comfino-iframe-container"></div>
<?php if ($render_init_script): ?>
<input id="comfino-loan-amount" name="comfino_loan_amount" type="hidden" />
<input id="comfino-loan-type" name="comfino_loan_type" type="hidden" />
<input id="comfino-loan-term" name="comfino_loan_term" type="hidden" />
<script data-cmp-ab="2">ComfinoPaywall.initIframe();</script>
<?php endif; ?>
