<?php

namespace Comfino\View;

use Comfino\Api\ApiClient;
use Comfino\Api\HttpErrorExceptionInterface;
use Comfino\Common\Frontend\FrontendHelper;
use Comfino\Common\Frontend\ProductWidgetScriptHelper;
use Comfino\Configuration\ConfigManager;
use Comfino\DebugLogger;
use Comfino\ErrorLogger;
use Comfino\Extended\Api\Dto\Plugin\OperationContext;
use Comfino\Frontend\WooCommerceShopEnvironmentBuilder;
use Comfino\Main;
use Comfino\PaymentGateway;
use Comfino\Extended\Auth\PaywallAuthTokenGenerator;

if (!defined('ABSPATH')) {
    exit;
}

final class FrontendManager
{
    public static function getAuthToken(): string
    {
        $widgetKey = ConfigManager::getWidgetKey() ?? '';
        $apiKey = ConfigManager::getApiKey() ?? '';

        if (empty($apiKey) || empty($widgetKey)) {
            return '';
        }

        return PaywallAuthTokenGenerator::generateAuthToken($widgetKey, $apiKey);
    }

    public static function getLoggingToken(): string
    {
        $widgetKey = ConfigManager::getWidgetKey() ?? '';
        $accessToken = ConfigManager::getErrorLoggingAccessToken();

        if (empty($accessToken) || empty($widgetKey)) {
            return '';
        }

        return PaywallAuthTokenGenerator::generateLoggingToken($widgetKey, $accessToken);
    }

    public static function getTrackId(): string
    {
        return ApiClient::getInstance()->getTrackId();
    }

    public static function renderAdminLogo(): string
    {
        return FrontendHelper::renderAdminLogo(
            ConfigManager::getLogoApiHost(),
            'WC',
            WC_VERSION,
            PaymentGateway::VERSION,
            PaymentGateway::BUILD_TS,
            'width: 300px',
            'Comfino logo'
        );
    }

    public static function renderHiddenInput(string $fieldKey, ?string $fieldValue, array $data, \WC_Settings_API $wcSettings): string
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
            disabled($data['disabled'], true, false),
            $wcSettings->get_custom_attribute_html($data)
        );
    }

    public static function renderCheckboxSet(string $fieldKey, ?array $fieldValue, array $data, \WC_Settings_API $wcSettings): string
    {
        $defaults = [
            'title' => '',
            'label' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => [],
        ];

        $data = wp_parse_args($data, $defaults);

        if (!$data['label']) {
            $data['label'] = $data['title'];
        }

        if (!isset($data['values']) || !is_array($data['values'])) {
            return '';
        }

        if ($fieldValue === null) {
            $fieldValue = [];
        }

        $inputs = [];

        foreach ($data['values'] as $valueKey => $valueName) {
            $fieldName = esc_attr($fieldKey . '[' . $valueKey . ']');
            $inputs[] = sprintf(
                '<label for="%s"><input %s class="%s" type="checkbox" name="%s" id="%s" style="%s" value="%s" %s %s /> %s</label>', // WPCS: XSS ok.
                $fieldName,
                disabled($data['disabled'], true, false),
                esc_attr($data['class']),
                $fieldName,
                $fieldName,
                esc_attr($data['css']),
                $valueKey,
                checked(in_array($valueKey, $fieldValue, true) ? 'yes' : 'no', 'yes', false),
                $wcSettings->get_custom_attribute_html($data),
                wp_kses_post($valueName)
            );
        }

        return sprintf(
            '<tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="%s">%s %s</label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>%s</span></legend>
                        %s
                        <br/>%s
                    </fieldset>
                </td>
		    </tr>', // WPCS: XSS ok.
            esc_attr($fieldKey),
            wp_kses_post($data['title']),
            $wcSettings->get_tooltip_html($data),
            wp_kses_post($data['title']),
            implode('<br/>', $inputs),
            $wcSettings->get_description_html($data)
        );
    }

    public static function renderAllowedProductsConfig(array $data): string
    {
        $defaults = [
            'title' => '',
            'type' => 'allowed_products_config',
            'product_types' => [],
            'saved_config' => [],
        ];

        $data = wp_parse_args($data, $defaults);

        return sprintf(
            '<tr valign="top"><td class="forminp" colspan="2"><h3>%s</h3>%s</td></tr>',
            esc_html($data['title']),
            TemplateManager::renderView(
                'allowed-products-config',
                'admin/_configure',
                ['product_types' => $data['product_types'], 'saved_config'  => $data['saved_config']],
                false
            )
        );
    }

    public static function renderCartValueLimitsConfig(array $data): string
    {
        $defaults = [
            'title' => '',
            'type' => 'cart_value_limits_config',
            'description' => '',
            'product_types' => [],
            'saved_config' => [],
        ];

        $data = wp_parse_args($data, $defaults);

        return sprintf(
            '<tr valign="top"><td class="forminp" colspan="2">%s</td></tr>',
            TemplateManager::renderView(
                'cart-value-limits-config',
                'admin/_configure',
                [
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'product_types' => $data['product_types'],
                    'saved_config' => $data['saved_config'],
                ],
                false
            )
        );
    }

    public static function renderProductCategoryFilterGroup(array $data): string
    {
        $defaults = [
            'title' => '',
            'type' => 'product_category_filter_group',
            'id' => 'product_categories',
            'description' => '',
            'product_types' => [],
        ];

        $data = wp_parse_args($data, $defaults);

        return sprintf(
            '<tr valign="top"><td class="forminp" colspan="2">%s</td></tr>',
            TemplateManager::renderView(
                'product-category-filter-group',
                'admin/_configure',
                [
                    'title' => $data['title'],
                    'tree_id' => $data['id'],
                    'description' => $data['description'],
                    'product_types' => $data['product_types'],
                ],
                false
            )
        );
    }

    public static function renderProductIdFilter(array $data): string
    {
        $defaults = [
            'title' => '',
            'type' => 'product_id_filter',
            'description' => '',
            'product_ids' => [],
        ];

        $data = wp_parse_args($data, $defaults);

        return sprintf(
            '<tr valign="top"><td class="forminp" colspan="2">%s</td></tr>',
            TemplateManager::renderView(
                'product-id-filter',
                'admin/_configure',
                [
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'product_ids' => $data['product_ids'],
                ],
                false
            )
        );
    }

    public static function getLocalStyleUrl(string $styleFileName, bool $frontStyle = true): string
    {
        global $comfino_payment_gateway;

        $styleDirectory = ($frontStyle ? 'front' : 'admin');

        return $comfino_payment_gateway->plugin_url() . "/resources/css/$styleDirectory/$styleFileName";
    }

    /**
     * @param string[] $styles
     * @param string[][] $dependencies
     *
     * @return string[]
     */
    public static function includeLocalStyles(array $styles, array $dependencies = [], $version = null, bool $frontStyle = true): array
    {
        $styleIds = [];

        foreach ($styles as $styleName) {
            $styleId = 'comfino-style-' . str_replace('.', '-', strtolower(pathinfo($styleName, PATHINFO_FILENAME)));
            $styleIds[] = $styleId;

            wp_enqueue_style($styleId, self::getLocalStyleUrl($styleName, $frontStyle), $dependencies[$styleName] ?? [], $version);
        }

        return $styleIds;
    }

    public static function getLocalScriptUrl(string $scriptFileName, bool $frontScript = true): string
    {
        global $comfino_payment_gateway;

        $scriptDirectory = ($frontScript ? 'front' : 'admin');

        if (ConfigManager::useDevEnvVars() && ConfigManager::useUnminifiedScripts()) {
            $scriptFileName = str_replace('.min.js', '.js', $scriptFileName);

            if (!file_exists($comfino_payment_gateway->plugin_abspath() . "/resources/js/$scriptDirectory/$scriptFileName")) {
                $scriptFileName = str_replace('.js', '.min.js', $scriptFileName);
            }
        } elseif (strpos($scriptFileName, '.min.') === false) {
            $scriptFileName = str_replace('.js', '.min.js', $scriptFileName);
        }

        return $comfino_payment_gateway->plugin_url() . "/resources/js/$scriptDirectory/$scriptFileName";
    }

    /**
     * Base URL for SDK and checkout assets served from the dedicated sdk.* CDN host.
     */
    public static function getSdkCdnBaseUrl(): string
    {
        if (ConfigManager::useDevEnvVars() && getenv('COMFINO_DEV_SDK_CDN_BASE_URL')) {
            return sanitize_url(wp_unslash(getenv('COMFINO_DEV_SDK_CDN_BASE_URL')));
        }

        return ConfigManager::isSandboxMode() ? 'https://sdk.craty.pl' : 'https://sdk.comfino.pl';
    }

    public static function resetScripts(): void
    {
        wp_scripts()->registered = [];
        wp_scripts()->queue = [];
    }

    public static function resetStyles(): void
    {
        wp_styles()->registered = [];
        wp_styles()->queue = [];
    }

    /**
     * @param string[] $dependencies
     */
    public static function embedInlineScript(string $scriptId, string $scriptContents, array $dependencies = [], bool $inFooter = false, $version = null): void
    {
        wp_register_script($scriptId, '', $dependencies, $version, ['in_footer' => $inFooter]);
        wp_enqueue_script($scriptId);
        wp_add_inline_script($scriptId, $scriptContents);
    }

    /**
     * @param string[] $scripts
     * @param string[][] $dependencies
     *
     * @return string[]
     */
    public static function includeLocalScripts(array $scripts, array $dependencies = [], bool $frontScript = true, bool $inFooter = true, $version = null): array
    {
        $scriptIds = [];

        foreach ($scripts as $scriptName) {
            $scriptId = 'comfino-script-' . str_replace('.', '-', strtolower(pathinfo($scriptName, PATHINFO_FILENAME)));
            $scriptIds[] = $scriptId;

            wp_enqueue_script(
                $scriptId,
                self::getLocalScriptUrl($scriptName, $frontScript),
                $dependencies[$scriptName] ?? [],
                $version,
                ['in_footer' => $inFooter]
            );
        }

        return $scriptIds;
    }

    /**
     * @param string[] $scripts
     * @param string[][] $dependencies
     *
     * @return string[]
     */
    public static function registerLocalScripts(array $scripts, array $dependencies = [], bool $frontScript = true, bool $inFooter = true, $version = null): array
    {
        $scriptIds = [];

        foreach ($scripts as $scriptName) {
            $scriptId = 'comfino-script-' . str_replace('.', '-', strtolower(pathinfo($scriptName, PATHINFO_FILENAME)));
            $scriptIds[] = $scriptId;

            wp_register_script(
                $scriptId,
                self::getLocalScriptUrl($scriptName, $frontScript),
                $dependencies[$scriptName] ?? [],
                $version,
                ['in_footer' => $inFooter]
            );
        }

        return $scriptIds;
    }

    /**
     * Renders the product-page widget config block consumed by the CDN product widget script (`comfino-woocommerce-widget.min.js`).
     * Emits a `<script type="application/json" id="comfino-widget-config">` element whose JSON matches the SDK's
     * WidgetConfig contract; the deferred script reads it, imports the SDK, and calls sdk.bootstrapWidget(). Replaces
     * the legacy inline widget-frontend init previously embedded in wp_head.
     *
     * The config array is filtered against the shared `ProductWidgetScriptHelper::WIDGET_CONFIG_KEYS` allowlist
     * (also drops nulls) and JSON-encoded with the same defensive flags the SDK init helpers use, so any
     * admin-controlled string (selectors, product names in productCartDetails) cannot terminate the script tag,
     * escape the JSON string, or smuggle entity references.
     *
     * @param int|null $productId Current product id, or null when unavailable
     *
     * @return string The `<script type="application/json" id="comfino-widget-config">…</script>` block
     */
    public static function renderWidgetConfigElement(?int $productId): string
    {
        try {
            $settings = ConfigManager::getConfigurationValues(
                'widget_settings',
                [
                    'COMFINO_WIDGET_KEY',
                    'COMFINO_WIDGET_PRICE_SELECTOR',
                    'COMFINO_WIDGET_PRICE_ATTRIBUTE',
                    'COMFINO_WIDGET_TARGET_SELECTOR',
                    'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR',
                    'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL',
                    'COMFINO_WIDGET_TYPE',
                    'COMFINO_WIDGET_OFFER_TYPES',
                    'COMFINO_WIDGET_EMBED_METHOD',
                    'COMFINO_WIDGET_SHOW_PROVIDER_LOGOS',
                    'COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL',
                    'COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL',
                    'COMFINO_WIDGET_DISABLE_BANNER',
                    'COMFINO_WIDGET_CALCULATOR_TRIGGER_SELECTOR',
                ]
            );

            $variables = ConfigManager::getWidgetVariables($productId);

            // getWidgetVariables() emits the string literal 'null' for absent fields; normalize to real null.
            $notNull = static function ($value) {
                return $value === 'null' ? null : $value;
            };

            $offerTypesValue = $settings['COMFINO_WIDGET_OFFER_TYPES'] ?? [];
            $offerTypesList = is_array($offerTypesValue) ? $offerTypesValue : explode(',', (string) $offerTypesValue);

            $offerTypes = array_values(array_filter(
                array_map('trim', $offerTypesList),
                static function (string $type): bool {
                    return $type !== '';
                }
            ));

            // getWidgetVariables() reports PRODUCT_PRICE as a PLN float; the SDK expects grosze (smallest unit).
            $priceFloat = $notNull($variables['PRODUCT_PRICE'] ?? null);
            $price = $priceFloat === null ? null : (int) round(((float) $priceFloat) * 100);

            $config = [
                'sdkScriptUrl' => ConfigManager::getSdkScriptUrl(),
                'environment' => ConfigManager::isSandboxMode() ? 'sandbox' : 'production',
                'widgetKey' => $settings['COMFINO_WIDGET_KEY'] ?? null,
                'loggingToken' => $variables['LOGGING_TOKEN'] ?? null,
                'trackId' => $variables['TRACK_ID'] ?? null,
                'widgetTargetSelector' => $settings['COMFINO_WIDGET_TARGET_SELECTOR'] ?? null,
                'priceSelector' => $settings['COMFINO_WIDGET_PRICE_SELECTOR'] ?? null,
                'priceAttribute' => ($settings['COMFINO_WIDGET_PRICE_ATTRIBUTE'] ?? '') ?: null,
                'priceObserverSelector' => ($settings['COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR'] ?? '') ?: null,
                'priceObserverLevel' => (int) ($settings['COMFINO_WIDGET_PRICE_OBSERVER_LEVEL'] ?? 0),
                'embedMethod' => $settings['COMFINO_WIDGET_EMBED_METHOD'] ?? null,
                'widgetType' => $settings['COMFINO_WIDGET_TYPE'] ?? null,
                'offerTypes' => $offerTypes !== [] ? $offerTypes : null,
                'showProviderLogos' => (bool) ($settings['COMFINO_WIDGET_SHOW_PROVIDER_LOGOS'] ?? false),
                'hasPriceInput' => false,
                'bannerCssUrl' => ($settings['COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL'] ?? '') ?: null,
                'calculatorCssUrl' => ($settings['COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL'] ?? '') ?: null,
                /* Standalone calculator (no banner): the shop's own button/link opens the Comfino calculator window,
                   either via triggerSelector (config bridge wires the click) or window.comfinoWidget.open() / the
                   comfino:widget:ready event. */
                'components' => !empty($settings['COMFINO_WIDGET_DISABLE_BANNER']) ? 'calculator' : null,
                'triggerSelector' => ($settings['COMFINO_WIDGET_CALCULATOR_TRIGGER_SELECTOR'] ?? '') ?: null,
                'price' => $price,
                'productId' => $notNull($variables['PRODUCT_ID'] ?? null),
                'availableProductTypes' => $variables['AVAILABLE_PRODUCT_TYPES'] ?? null,
                'productCartDetails' => $notNull($variables['PRODUCT_CART_DETAILS'] ?? null),
                'language' => $variables['LANGUAGE'] ?? null,
                'currency' => $variables['CURRENCY'] ?? null,
                'shopEnvironment' => array_merge(
                    WooCommerceShopEnvironmentBuilder::createDefault()->buildForFrontend(['type' => 'product']),
                    [
                        'language' => $variables['LANGUAGE'] ?? Main::getShopLanguage(),
                        'currency' => $variables['CURRENCY'] ?? Main::getShopCurrency(),
                    ]
                ),
            ];

            // Drops nulls and anything outside WIDGET_CONFIG_KEYS, so omitted options fall through to the SDK / CDN-profile defaults.
            $config = ProductWidgetScriptHelper::buildConfig($config);

            $json = wp_json_encode(
                $config,
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
            );

            if ($json === false) {
                return '';
            }

            return '<script type="application/json" id="' . ProductWidgetScriptHelper::CONFIG_ELEMENT_ID . '">' . $json . '</script>';
        } catch (\Throwable $e) {
            self::processError('Widget config element', $e, null, null, null, '[ERROR]', OperationContext::WidgetRendering);
            ErrorLogger::sendError(
                $e,
                OperationContext::WidgetRendering,
                (string) $e->getCode(),
                $e->getMessage(),
                $e instanceof HttpErrorExceptionInterface ? $e->getUrl() : null,
                $e instanceof HttpErrorExceptionInterface ? $e->getRequestBody() : null,
                $e instanceof HttpErrorExceptionInterface ? $e->getResponseBody() : null,
                $e->getTraceAsString()
            );
        }

        return '';
    }

    /**
     * Unified error processing method for handling exceptions consistently across the module.
     *
     * @param string $errorPrefix Short description of error context.
     * @param \Throwable $exception Exception to process.
     * @param int|null $httpStatus Optional HTTP status code to set in response.
     * @param string|null $userErrorMessage Optional custom user-friendly error message.
     *
     * @return array Array with 'title' (user error message) and 'language' (shop language code).
     */
    public static function processError(
        string $errorPrefix,
        \Throwable $exception,
        ?int $httpStatus = null,
        ?string $userErrorMessage = null,
        ?array $parameters = null,
        string $eventPrefix = '[ERROR]',
        string $context = OperationContext::Unknown
    ): array
    {
        DebugLogger::logEvent(
            $eventPrefix,
            $errorPrefix,
            array_merge(
                [
                    'exception' => get_class($exception),
                    'error_message' => $exception->getMessage(),
                    'error_code' => $exception->getCode(),
                    'error_file' => $exception->getFile(),
                    'error_line' => $exception->getLine(),
                    'error_trace' => $exception->getTraceAsString(),
                ],
                $parameters ?? []
            )
        );

        ErrorLogger::sendError(
            $exception,
            $context,
            (string) $exception->getCode(),
            $exception->getMessage(),
            $exception instanceof HttpErrorExceptionInterface ? $exception->getUrl() : null,
            $exception instanceof HttpErrorExceptionInterface ? $exception->getRequestBody() : null,
            $exception instanceof HttpErrorExceptionInterface ? $exception->getResponseBody() : null,
            $exception->getTraceAsString()
        );

        if (empty($userErrorMessage)) {
            $userErrorMessage = __(
                'There was a technical problem. Please try again in a moment and it should work!',
                'comfino-payment-gateway'
            );
        }

        if ($httpStatus !== null) {
            http_response_code($httpStatus);
        }

        return ['title' => $userErrorMessage, 'language' => Main::getShopLanguage()];
    }

    public static function getImageAllowedHtml(): array
    {
        return ['img' => ['src' => [], 'style' => [], 'alt' => []]];
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
                'input' => ['id' => [], 'name' => [], 'value' => [], 'class' => [], 'style' => [], 'title' => [], 'placeholder' => [], 'type' => [], 'checked' => [], 'readonly' => [], 'disabled' => [], 'required' => [], 'data-comfino-max-select' => []],
                'textarea' => ['id' => [], 'name' => [], 'class' => [], 'style' => [], 'title' => [], 'placeholder' => [], 'rows' => [], 'cols' => [], 'readonly' => [], 'disabled' => [], 'required' => []],
                'select' => ['id' => [], 'name' => [], 'multiple' => [], 'disabled' => [], 'required' => []],
                'option' => ['value' => [], 'selected' => [], 'label' => [], 'disabled' => []],
                'details' => ['id' => [], 'class' => [], 'style' => [], 'open' => []],
                'summary' => ['id' => [], 'class' => [], 'style' => []],
            ],
            self::getAllowedScriptHtml(),
            self::getAllowedStyleHtml()
        );
    }
}
