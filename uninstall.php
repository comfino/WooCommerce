<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (is_readable(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';

    Comfino\Main::uninstall(__DIR__, str_replace('uninstall', 'comfino-payment-gateway', __FILE__));
}
