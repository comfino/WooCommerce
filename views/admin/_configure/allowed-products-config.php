<?php
/**
 * Template for allowed financial products configuration table.
 *
 * Renders a table of financial product types with per-type term limit controls
 * (minimum term, maximum term, specific allowed terms). Rendered inside a
 * WooCommerce settings row by FrontendManager::renderAllowedProductsConfig().
 *
 * @var array $product_types [typeCode => typeName, ...]
 * @var array $saved_config [typeCode => ['maxTerm' => int|null, 'minTerm' => int|null, 'terms' => int[]|null], ...]
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<table class="widefat comfino-term-limits-table" style="margin-top: 10px">
    <thead>
        <tr>
            <th scope="col" style="padding-left: 10px"><?= esc_html__('Product type', 'comfino-payment-gateway') ?></th>
            <th scope="col" style="padding-left: 10px"><?= esc_html__('Min term', 'comfino-payment-gateway') ?></th>
            <th scope="col" style="padding-left: 10px"><?= esc_html__('Max term', 'comfino-payment-gateway') ?></th>
            <th scope="col" style="padding-left: 10px"><?= esc_html__('Specific terms (comma-separated)', 'comfino-payment-gateway') ?></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($product_types as $typeCode => $typeName): ?>
        <?php
            $saved = $saved_config[$typeCode] ?? [];
            $minTerm = isset($saved['minTerm']) ? (int)$saved['minTerm'] : '';
            $maxTerm = isset($saved['maxTerm']) ? (int)$saved['maxTerm'] : '';
            $terms = isset($saved['terms']) && is_array($saved['terms']) ? implode(',', $saved['terms']) : '';
        ?>
        <tr>
            <td><strong><?= esc_html($typeName) ?></strong><br><code><?= esc_html($typeCode) ?></code></td>
            <td>
                <input type="number" min="1" max="999"
                    name="comfino_term_limits[<?= esc_attr($typeCode) ?>][minTerm]"
                    value="<?= esc_attr($minTerm) ?>"
                    placeholder="<?= esc_attr__('No limit', 'comfino-payment-gateway') ?>"
                    style="width: 140px"
                />
            </td>
            <td>
                <input type="number" min="1" max="999"
                    name="comfino_term_limits[<?= esc_attr($typeCode) ?>][maxTerm]"
                    value="<?= esc_attr($maxTerm) ?>"
                    placeholder="<?= esc_attr__('No limit', 'comfino-payment-gateway') ?>"
                    style="width: 140px"
                />
            </td>
            <td>
                <input type="text"
                    name="comfino_term_limits[<?= esc_attr($typeCode) ?>][terms]"
                    value="<?= esc_attr($terms) ?>"
                    placeholder="<?= esc_attr__('e.g. 6,12,24,36', 'comfino-payment-gateway') ?>"
                    style="width: 200px"
                />
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="description">
    <?= esc_html__('Leave fields empty to apply no restriction for that product type. All constraints are cumulative: when "Specific terms" is set, only those exact terms are offered; "Min/Max term" further narrows them down.', 'comfino-payment-gateway') ?>
</p>
