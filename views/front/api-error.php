<?php

if (!defined('ABSPATH')) {
    exit;
}

/** @var string $error_message */
/** @var string $url */
/** @var string $request_body */
/** @var string $response_body */
/** @var bool $is_debug_mode */
?>
<?php echo esc_html($error_message); ?>
<?php if ($is_debug_mode): ?>
<h2>API error</h2>
<?php echo esc_html($error_message); ?>
<p>URL: <?php echo esc_html($url); ?></p>
<p>Request:</p>
<code><?php echo esc_html($request_body); ?></code>
<p>Response:</p>
<code><?php echo esc_html($response_body); ?></code>
<?php endif; ?>
