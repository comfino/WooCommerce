<?php

if (!defined('ABSPATH')) {
    exit;
}

/** @var string $tree_id */
/** @var string $product_type */
/** @var string $tree_nodes */
/** @var int $close_depth */
?>
<div id="<?php esc_html($tree_id); ?>_<?php esc_html($product_type); ?>"></div>
<input id="<?php esc_html($tree_id); ?>_<?php esc_html($product_type); ?>_input" name="<?php esc_html($tree_id); ?>[<?php esc_html($product_type); ?>]" type="hidden" />
<script>
    new Tree(
        '#<?php esc_html($tree_id); ?>_<?php esc_html($product_type); ?>',
        {
            data: <?php esc_html($tree_nodes); ?>,
            closeDepth: <?php esc_html($close_depth); ?>,
            onChange: function () {
                document.getElementById('<?php esc_html($tree_id); ?>_<?php esc_html($product_type); ?>_input').value = this.values.join();
            }
        }
    );
</script>
