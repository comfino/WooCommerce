<?php
/**
 * Template for the collapsible product ID filter (global blacklist).
 *
 * A single text field where the admin enters comma-separated product IDs that should
 * disable all Comfino financial products when present in the cart. Uses a native
 * <details> accordion (no JS required).
 *
 * @var string $title Accordion label
 * @var string $description Field description
 * @var int[] $product_ids Currently excluded product IDs
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<details class="comfino-filter-accordion">
    <summary style="cursor: pointer; font-size: 14px; font-weight: 600; padding: 8px 0;"><?php echo esc_html($title) ?></summary>
    <div style="padding: 8px 0 0 0;">
        <?php if (!empty($description)): ?>
            <p class="description"><?php echo esc_html($description) ?></p>
        <?php endif; ?>
        <textarea
            id="comfino_product_id_filter"
            name="comfino_product_id_filter"
            rows="3"
            class="input-text wide-input"
            style="width: 100%; max-width: 600px;"
            placeholder="<?php echo esc_attr__('e.g. 12, 45, 108', 'comfino-payment-gateway') ?>"
        ><?php echo esc_textarea(implode(', ', array_map('intval', $product_ids))) ?></textarea>
    </div>
</details>
