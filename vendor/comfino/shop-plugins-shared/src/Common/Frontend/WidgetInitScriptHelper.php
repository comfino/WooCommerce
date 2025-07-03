<?php

namespace Comfino\Common\Frontend;

class WidgetInitScriptHelper
{
    public const WIDGET_INIT_PARAMS = [
        'WIDGET_KEY',
        'WIDGET_PRICE_SELECTOR',
        'WIDGET_TARGET_SELECTOR',
        'WIDGET_PRICE_OBSERVER_SELECTOR',
        'WIDGET_PRICE_OBSERVER_LEVEL',
        'WIDGET_TYPE',
        'OFFER_TYPES',
        'EMBED_METHOD',
        'SHOW_PROVIDER_LOGOS',
    ];

    public const WIDGET_INIT_VARIABLES = [
        'WIDGET_SCRIPT_URL',
        'PRODUCT_ID',
        'PRODUCT_PRICE',
        'PLATFORM',
        'PLATFORM_VERSION',
        'PLATFORM_DOMAIN',
        'PLUGIN_VERSION',
        'AVAILABLE_PRODUCT_TYPES',
        'PRODUCT_CART_DETAILS',
        'LANGUAGE',
        'CURRENCY',
    ];

    /**
     * @throws \InvalidArgumentException
     * @param string $widgetInitCode
     * @param mixed[] $widgetInitParams
     * @param mixed[] $widgetInitVariables
     */
    public static function renderWidgetInitScript($widgetInitCode, $widgetInitParams, $widgetInitVariables): string
    {
        $widgetInitParamsAssocKeys = array_flip(self::WIDGET_INIT_PARAMS);
        $widgetInitVariablesAssocKeys = array_flip(self::WIDGET_INIT_VARIABLES);

        if (count(array_intersect_key($widgetInitParamsAssocKeys, $widgetInitParams)) !== count(self::WIDGET_INIT_PARAMS)) {
            throw new \InvalidArgumentException('Invalid widget initialization parameters.');
        }

        if (count(array_intersect_key($widgetInitVariablesAssocKeys, $widgetInitVariables)) !== count(self::WIDGET_INIT_VARIABLES)) {
            throw new \InvalidArgumentException('Invalid widget initialization variables.');
        }

        return str_replace(
            array_map(
                static function (string $widgetInitParamName) : string {
                    return '{' . $widgetInitParamName . '}';
                },
                array_merge(self::WIDGET_INIT_PARAMS, array_keys($widgetInitVariables))
            ),
            array_map(
                static function ($varValue): string {
                    if (is_bool($varValue)) {
                        return $varValue ? 'true' : 'false';
                    }

                    if (is_array($varValue)) {
                        return ($result = json_encode($varValue)) !== false ? $result : '[]';
                    }

                    return $varValue !== null ? (string) $varValue : 'null';
                },
                array_merge(
                    array_merge($widgetInitParamsAssocKeys, $widgetInitParams),
                    array_values($widgetInitVariables)
                )
            ),
            $widgetInitCode
        );
    }

    /**
     * @param string $widgetInitCode
     */
    public static function initScriptRequiresUpdate($widgetInitCode): bool
    {
        return md5($widgetInitCode) !== md5(self::getInitialWidgetCode());
    }

    public static function getInitialWidgetCodeHash(): string
    {
        return md5(self::getInitialWidgetCode());
    }

    public static function getInitialWidgetCode(): string
    {
        return trim("
const script = document.createElement('script');
script.onload = function () {
    ComfinoWidgetFrontend.init({
        widgetKey: '{WIDGET_KEY}',
        priceSelector: '{WIDGET_PRICE_SELECTOR}',
        widgetTargetSelector: '{WIDGET_TARGET_SELECTOR}',
        priceObserverSelector: '{WIDGET_PRICE_OBSERVER_SELECTOR}',
        priceObserverLevel: {WIDGET_PRICE_OBSERVER_LEVEL},
        type: '{WIDGET_TYPE}',
        offerTypes: {OFFER_TYPES},
        embedMethod: '{EMBED_METHOD}',
        numOfInstallments: 0,
        price: null,
        productId: {PRODUCT_ID},
        productPrice: {PRODUCT_PRICE},
        platform: '{PLATFORM}',
        platformName: '{PLATFORM_NAME}',
        platformVersion: '{PLATFORM_VERSION}',
        platformDomain: '{PLATFORM_DOMAIN}',
        pluginVersion: '{PLUGIN_VERSION}',
        availableProductTypes: {AVAILABLE_PRODUCT_TYPES},
        productCartDetails: {PRODUCT_CART_DETAILS},
        language: '{LANGUAGE}',
        currency: '{CURRENCY}',
        showProviderLogos: {SHOW_PROVIDER_LOGOS},
        callbackBefore: function () {},
        callbackAfter: function () {},
        onOfferRendered: function (jsonResponse, widgetTarget, widgetNode) { },
        onWidgetBannerLoaded: function (loadedOffers) { },
        onWidgetCalculatorLoaded: function (loadedOffers) { },
        onGetPriceElement: function (priceSelector, priceObserverSelector) { return null; },
        debugMode: window.location.hash && window.location.hash.substring(1) === 'comfino_debug'
    });
};
script.src = '{WIDGET_SCRIPT_URL}';
script.async = true;
document.getElementsByTagName('head')[0].appendChild(script);
");
    }
}
