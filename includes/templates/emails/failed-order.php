<?php

defined('ABSPATH') || exit;

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email); ?>

<?php /* translators: %s: Customer first name */ ?>
    <p><?php printf(esc_html__('Hi %s,', 'comfino-payment-gateway'), esc_html($order->get_billing_first_name())); ?></p>

    <p><?php printf(esc_html__('We have noticed that your order number [%s] has still not been paid. The products you have selected are still waiting in your shopping cart.', 'comfino-payment-gateway'), '<b>' . esc_html($order->get_id()) . '</b>'); ?></p>
    <p><?php printf(esc_html__('Click on the link below and use the payments offered in the store. %s', 'comfino-payment-gateway'), '<a target="_blank" href="'.esc_url($order->get_checkout_payment_url()).'">'.esc_html__('Proceed to payment', 'comfino-payment-gateway').'<a/'); ?></p>
<?php

do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

do_action('woocommerce_email_footer', $email_heading, $email); ?>

