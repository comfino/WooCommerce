<?php
/**
 * Template for the product category filter tree.
 *
 * Displays an interactive category tree for filtering products by type.
 * Uses Tree.js library for tree rendering and selection.
 *
 * @see https://github.com/daweilv/treejs
 *
 * @var string $tree_id Tree element ID
 * @var string $product_type Product type code
 * @var array $tree_nodes Tree structure data
 * @var int $close_depth Initial depth for closed nodes
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="<?= esc_attr($tree_id) ?>_<?= esc_attr($product_type) ?>"></div>
<input id="<?= esc_attr($tree_id) ?>_<?= esc_attr($product_type) ?>_input" name="<?= esc_attr($tree_id) ?>[<?= esc_attr($product_type) ?>]" type="hidden" data-initialized="no" />
<script>
    new Tree(
        '#<?= esc_js($tree_id) ?>_<?= esc_js($product_type) ?>',
        {
            data: <?= wp_json_encode($tree_nodes) ?>,
            closeDepth: <?= esc_js($close_depth) ?>,
            onChange: function () {
                let input = document.getElementById('<?= esc_js($tree_id) ?>_<?= esc_js($product_type) ?>_input');
                input.value = this.values.join();

                if (input.dataset.initialized === 'no') {
                    input.dataset.initialized = 'yes';
                } else {
                    input.dispatchEvent(new Event('change'));
                }
            }
        }
    );
</script>
