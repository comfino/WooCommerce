<?php

namespace Comfino;

use Comfino\Configuration\ConfigManager;
use Comfino\Extended\Api\Serializer\Json as JsonSerializer;

if (!defined('ABSPATH')) {
    exit;
}

class DebugLogger
{
    /** @var Common\Backend\DebugLogger */
    private static $debugLogger;

    public static function getLoggerInstance(): Common\Backend\DebugLogger
    {
        if (self::$debugLogger === null) {
            self::$debugLogger = Common\Backend\DebugLogger::getInstance(
                new JsonSerializer(),
                Main::getPluginDirectory() . '/var/log/debug.log'
            );
        }

        return self::$debugLogger;
    }

    public static function logEvent(string $eventPrefix, string $eventMessage, ?array $parameters = null): void
    {
        if ((!isset($_COOKIE['COMFINO_SERVICE_SESSION']) || $_COOKIE['COMFINO_SERVICE_SESSION'] !== 'ACTIVE')
            && ConfigManager::isServiceMode()
        ) {
            return;
        }

        if (ConfigManager::isDebugMode()) {
            self::getLoggerInstance()->logEvent($eventPrefix, $eventMessage, $parameters);
        }
    }
}
