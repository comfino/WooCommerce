<?php

if (!defined('ABSPATH')) {
    exit;
}

/** @see https://github.com/daweilv/treejs */

/** @var string $tree_id */
/** @var string $product_type */
/** @var array $tree_nodes */
/** @var int $close_depth */
?>
<div id="<?php echo esc_attr($tree_id); ?>_<?php echo esc_attr($product_type); ?>"></div>
<input id="<?php echo esc_attr($tree_id); ?>_<?php echo esc_attr($product_type); ?>_input" name="<?php echo esc_attr($tree_id); ?>[<?php echo esc_attr($product_type); ?>]" type="hidden" data-initialized="no" />
<script>
    new Tree(
        '#<?php echo esc_js($tree_id); ?>_<?php echo esc_js($product_type); ?>',
        {
            data: <?php echo wp_json_encode($tree_nodes); ?>,
            closeDepth: <?php echo esc_js($close_depth); ?>,
            onChange: function () {
                let input = document.getElementById('<?php echo esc_js($tree_id); ?>_<?php echo esc_js($product_type); ?>_input');
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
