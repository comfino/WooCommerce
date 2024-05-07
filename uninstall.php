<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/includes/comfino-core.php';
require_once __DIR__ . '/includes/comfino-api-client.php';
require_once __DIR__ . '/includes/comfino-config-manager.php';
require_once __DIR__ . '/comfino-payment-gateway.php';

use Comfino\Api_Client;
use Comfino\Config_Manager;

$config_manager = new Config_Manager();

Api_Client::init($config_manager);
Api_Client::notify_plugin_removal();

$config_manager->remove_configuration_options();
