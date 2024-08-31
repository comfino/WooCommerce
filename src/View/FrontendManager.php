<?php

namespace Comfino\View;

use Comfino\Api\ApiClient;
use Comfino\Api\ApiService;
use Comfino\CacheManager;
use Comfino\Common\Frontend\PaywallIframeRenderer;
use Comfino\Common\Frontend\PaywallRenderer;
use Comfino\Configuration\ConfigManager;
use Comfino\ErrorLogger;
use Comfino\TemplateRenderer\PluginRendererStrategy;

if (!defined('ABSPATH')) {
    exit;
}

final class FrontendManager
{
    public static function getPaywallRenderer(): PaywallRenderer
    {
        return new PaywallRenderer(
            ApiClient::getInstance(),
            CacheManager::getCachePool(),
            new PluginRendererStrategy(),
            ApiService::getEndpointUrl('cacheInvalidate'),
            ApiService::getEndpointUrl('configuration')
        );
    }

    public static function getPaywallIframeRenderer(): PaywallIframeRenderer
    {
        return new PaywallIframeRenderer(
            ApiClient::getInstance(),
            CacheManager::getCachePool(),
            new PluginRendererStrategy(),
            'WooCommerce',
            WC_VERSION,
            ApiService::getEndpointUrl('cacheInvalidate'),
            ApiService::getEndpointUrl('configuration')
        );
    }

    public static function renderWidgetInitCode(?int $productId): string
    {
        try {
            $widgetVariables = ConfigManager::getWidgetVariables($productId);

            $code = str_replace(
                array_merge(
                    [
                        '{WIDGET_KEY}',
                        '{WIDGET_PRICE_SELECTOR}',
                        '{WIDGET_TARGET_SELECTOR}',
                        '{WIDGET_PRICE_OBSERVER_SELECTOR}',
                        '{WIDGET_PRICE_OBSERVER_LEVEL}',
                        '{WIDGET_TYPE}',
                        '{OFFER_TYPE}',
                        '{EMBED_METHOD}',
                    ],
                    array_keys($widgetVariables)
                ),
                array_merge(
                    ConfigManager::getConfigurationValues(
                        'widget_settings',
                        [
                            'COMFINO_WIDGET_KEY',
                            'COMFINO_WIDGET_PRICE_SELECTOR',
                            'COMFINO_WIDGET_TARGET_SELECTOR',
                            'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR',
                            'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL',
                            'COMFINO_WIDGET_TYPE',
                            'COMFINO_WIDGET_OFFER_TYPE',
                            'COMFINO_WIDGET_EMBED_METHOD',
                        ]
                    ),
                    array_values($widgetVariables)
                ),
                ConfigManager::getCurrentWidgetCode($productId)
            );

            return '<script>' . str_replace(
                ['&#039;', '&gt;', '&amp;', '&quot;', '&#34;'],
                ["'", '>', '&', '"', '"'],
                esc_html($code)
            ) . '</script>';
        } catch (\Throwable $e) {
            ErrorLogger::sendError(
                'Widget script endpoint',
                $e->getCode(),
                $e->getMessage(),
                null,
                null,
                null,
                $e->getTraceAsString()
            );
        }

        return '';
    }
}
