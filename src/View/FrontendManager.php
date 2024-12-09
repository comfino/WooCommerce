<?php

namespace Comfino\View;

use Comfino\Api\ApiClient;
use Comfino\Api\ApiService;
use Comfino\Common\Frontend\FrontendHelper;
use Comfino\Common\Frontend\PaywallIframeRenderer;
use Comfino\Common\Frontend\PaywallRenderer;
use Comfino\Common\Frontend\WidgetInitScriptHelper;
use Comfino\Configuration\ConfigManager;
use Comfino\ErrorLogger;
use Comfino\PaymentGateway;
use Comfino\PluginShared\CacheManager;
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

    public static function renderAdminLogo(): string
    {
        return FrontendHelper::renderAdminLogo(
            ApiClient::getLogoApiHost(),
            'WC',
            WC_VERSION,
            PaymentGateway::VERSION,
            PaymentGateway::BUILD_TS,
            'width: 300px',
            'Comfino logo'
        );
    }

    public static function renderPaywallLogo(): string
    {
        return FrontendHelper::renderPaywallLogo(
            ApiClient::getLogoApiHost(),
            ApiClient::getInstance()->getApiKey(),
            ConfigManager::getWidgetKey(),
            'WC',
            WC_VERSION,
            PaymentGateway::VERSION,
            PaymentGateway::BUILD_TS,
            'height: 18px; margin: 0 5px',
            ConfigManager::getConfigurationValue('COMFINO_PAYMENT_TEXT')
        );
    }

    public static function renderHiddenInput(string $fieldKey, ?string $fieldValue, string $customAttributes, array $data): string
    {
        $defaults = [
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => [],
        ];

        $data = wp_parse_args($data, $defaults);

        return sprintf(
            '<input class="input-text regular-input %s" type="%s" name="%s" id="%s" style="%s" value="%s" placeholder="%s" %s %s />', // WPCS: XSS ok.
            esc_attr($data['class']),
            esc_attr($data['type']),
            esc_attr($fieldKey),
            esc_attr($fieldKey),
            esc_attr($data['css']),
            $fieldValue,
            esc_attr($data['placeholder']),
            disabled($data['disabled']),
            $customAttributes
        );
    }

    public static function renderProductCategoryTree(array $data): string
    {
        $defaults = [
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => [],
            'id' => '',
            'product_type' => '',
            'selected_categories' => [],
        ];

        $data = wp_parse_args($data, $defaults);

        return sprintf(
            '<tr valign="top"><td class="forminp" colspan="2"><h3>%s</h3>%s</td></tr>', // WPCS: XSS ok.
            esc_html($data['title']),
            SettingsForm::renderCategoryTree($data['id'], $data['product_type'], $data['selected_categories'])
        );
    }

    public static function renderWidgetInitCode(?int $productId): string
    {
        try {
            return str_replace(
                ['&#039;', '&gt;', '&amp;', '&quot;', '&#34;'],
                ["'", '>', '&', '"', '"'],
                WidgetInitScriptHelper::renderWidgetInitScript(
                    ConfigManager::getCurrentWidgetCode($productId),
                    array_combine(
                        [
                            'WIDGET_KEY',
                            'WIDGET_PRICE_SELECTOR',
                            'WIDGET_TARGET_SELECTOR',
                            'WIDGET_PRICE_OBSERVER_SELECTOR',
                            'WIDGET_PRICE_OBSERVER_LEVEL',
                            'WIDGET_TYPE',
                            'OFFER_TYPE',
                            'EMBED_METHOD',
                        ],
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
                        )
                    ),
                    ConfigManager::getWidgetVariables($productId)
                )
            );
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

    public static function getImageAllowedHtml(): array
    {
        return ['img' => ['src' => [], 'style' => [], 'alt' => []]];
    }

    public static function getPaywallIfarmeAllowedHtml(): array
    {
        return array_merge(
            [
                'iframe' => [
                    'id' => [],
                    'src' => [],
                    'class' => [],
                    'referrer-policy' => [],
                    'loading' => [],
                    'scrolling' => [],
                    'onload' => [],
                ],
            ],
            self::getAllowedScriptHtml(),
            self::getAllowedStyleHtml()
        );
    }

    public static function getAllowedScriptHtml(): array
    {
        return ['script' => ['id' => [], 'src' => [], 'type' => [], 'srcset' => [], 'async' => [], 'defer' => []]];
    }

    public static function getAllowedStyleHtml(): array
    {
        return ['style' => ['id' => [], 'link' => [], 'type' => [], 'media' => []]];
    }

    public static function getAdminPanelAllowedHtml(): array
    {
        return array_merge(
            wp_kses_allowed_html('post'),
            [
                'input' => ['id' => [], 'name' => [], 'value' => [], 'class' => [], 'style' => [], 'title' => [], 'placeholder' => [], 'type' => [], 'checked' => [], 'readonly' => [], 'disabled' => [], 'required' => []],
                'select' => ['id' => [], 'name' => [], 'multiple' => [], 'disabled' => [], 'required' => []],
                'option' => ['value' => [], 'selected' => [], 'label' => [], 'disabled' => []],
            ],
            self::getAllowedScriptHtml(),
            self::getAllowedStyleHtml()
        );
    }

    public static function getPaywallAllowedHtml(): array
    {
        return array_merge(
            wp_kses_allowed_html('post'),
            [
                'input' => ['id' => [], 'name' => [], 'value' => [], 'class' => [], 'style' => [], 'title' => [], 'placeholder' => [], 'type' => [], 'checked' => [], 'readonly' => [], 'disabled' => [], 'required' => []],
                'svg' => ['id' => [], 'version' => [], 'xmlns' => [], 'xmlns:xlink' => [], 'style' => [], 'width' => [], 'height' => [], 'x' => [], 'y' => [], 'viewBox' => [], 'xml:space' => []],
                'path' => ['id' => [], 'd' => [], 'fill' => []],
                'g' => ['id' => []],
            ],
            self::getAllowedScriptHtml(),
            self::getAllowedStyleHtml()
        );
    }
}
