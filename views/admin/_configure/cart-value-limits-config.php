<?php
/**
 * Template for the collapsible cart value limits per financial product type configuration table.
 *
 * Renders a table of financial product types with per-type minimum/maximum cart value controls, inside a native
 * <details> accordion (no JS required). Rendered inside a WooCommerce settings row by FrontendManager::renderCartValueLimitsConfig().
 *
 * @var string $title Accordion label
 * @var string $description Field description
 * @var array $product_types [typeCode => typeName, ...]
 * @var array $saved_config [typeCode => ['minAmount' => float|null, 'maxAmount' => float|null], ...]
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
        <table class="widefat comfino-cart-value-limits-table" style="margin-top: 10px">
            <thead>
                <tr>
                    <th scope="col" style="padding-left: 10px"><?php echo esc_html__('Product type', 'comfino-payment-gateway') ?></th>
                    <th scope="col" style="padding-left: 10px"><?php echo esc_html__('Min cart value', 'comfino-payment-gateway') ?></th>
                    <th scope="col" style="padding-left: 10px"><?php echo esc_html__('Max cart value', 'comfino-payment-gateway') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($product_types as $typeCode => $typeName): ?>
                <?php
                    $saved = $saved_config[$typeCode] ?? [];
                    $minAmount = isset($saved['minAmount']) ? (string)$saved['minAmount'] : '';
                    $maxAmount = isset($saved['maxAmount']) ? (string)$saved['maxAmount'] : '';
                ?>
                <tr>
                    <td><strong><?php echo esc_html($typeName) ?></strong><br><code><?php echo esc_html($typeCode) ?></code></td>
                    <td>
                        <input type="number" min="0" step="0.01"
                            name="comfino_cart_value_limits[<?php echo esc_attr($typeCode) ?>][minAmount]"
                            value="<?php echo esc_attr($minAmount) ?>"
                            placeholder="<?php echo esc_attr__('No limit', 'comfino-payment-gateway') ?>"
                            style="width: 140px"
                        />
                    </td>
                    <td>
                        <input type="number" min="0" step="0.01"
                            name="comfino_cart_value_limits[<?php echo esc_attr($typeCode) ?>][maxAmount]"
                            value="<?php echo esc_attr($maxAmount) ?>"
                            placeholder="<?php echo esc_attr__('No limit', 'comfino-payment-gateway') ?>"
                            style="width: 140px"
                        />
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</details>
