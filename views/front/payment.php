<?php
/**
 * Template for the Comfino payment method fields in checkout.
 *
 * Rendered by Main::renderPaywallIframe() as the container for the paywall. The CDN-hosted checkout glue script
 * (comfino-woocommerce.min.js) locates #comfino-paywall-container and renders the paywall iframe inside it.
 * Hidden inputs carry the selected loan type and term to the order submit handler.
 * Config is passed as a <script type="application/json"> element — not an executable script, no CSP issues.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** @var int $comfino_total_amount */
/** @var array<string, mixed>|null $comfino_checkout_config */
?>
<?php if (!empty($comfino_checkout_config)) : ?>
<script type="application/json" id="comfino-checkout-config"><?php echo wp_json_encode($comfino_checkout_config); ?></script>
<?php endif; ?>
<!-- Cart total in grosze; initial value set server-side, refreshed on cart/shipping changes via WooCommerce checkout fragments. -->
<input id="comfino-loan-amount" name="comfino_loan_amount" type="hidden" value="<?php echo esc_attr($comfino_total_amount); ?>" />
<!-- Loan parameters written by WooCommerceAdapter.updatePaymentState(), read on order placement. -->
<input id="comfino-loan-type" name="comfino_loan_type" type="hidden" value="" />
<input id="comfino-loan-term" name="comfino_loan_term" type="hidden" value="" />
<!-- Comfino web frontend SDK renders paywall iframe here. -->
<div id="comfino-paywall-container"></div>
