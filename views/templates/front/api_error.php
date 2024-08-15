<?php

if (!defined('ABSPATH')) {
    exit;
}

/** @var string $error_message */
/** @var string $url */
/** @var string $request_body */
/** @var string $response_body */
?>
<h2>API error</h2>
<?php esc_html($error_message); ?>
<p>URL: <?php esc_html($url); ?></p>
<p>Request:</p>
<code><?php esc_html($request_body); ?></code>
<p>Response:</p>
<code><?php esc_html($response_body); ?></code>
