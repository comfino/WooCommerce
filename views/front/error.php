<?php
/**
 * Template for an inline payment error message in checkout.
 *
 * Outputs a plain escaped error message when Comfino payment processing fails
 * (e.g., cart creation error). Displayed inline in the payment fields area.
 *
 * @var string $error_message User-facing error message to display
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<?php echo esc_html($error_message); ?>
