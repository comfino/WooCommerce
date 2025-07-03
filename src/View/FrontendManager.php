<?php

namespace Comfino\View;

use Comfino\Api\ApiClient;
use Comfino\Api\HttpErrorExceptionInterface;
use Comfino\Common\Frontend\FrontendHelper;
use Comfino\Common\Frontend\PaywallIframeRenderer;
use Comfino\Common\Frontend\PaywallRenderer;
use Comfino\Common\Frontend\WidgetInitScriptHelper;
use Comfino\Configuration\ConfigManager;
use Comfino\ErrorLogger;
use Comfino\Extended\Api\Serializer\Json as JsonSerializer;
use Comfino\PaymentGateway;

if (!defined('ABSPATH')) {
    exit;
}

final class FrontendManager
{
    public static function getPaywallRenderer(): PaywallRenderer
    {
        static $renderer = null;

        if ($renderer === null) {
            $renderer = new PaywallRenderer();
        }

        return $renderer;
    }

    public static function getPaywallIframeRenderer(): PaywallIframeRenderer
    {
        static $renderer = null;

        if ($renderer === null) {
            $renderer = new PaywallIframeRenderer();
        }

        return $renderer;
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

    public static function renderPaywallLogo(): string
    {
        return FrontendHelper::renderPaywallLogo(
            ConfigManager::getLogoApiHost(),
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
            disabled($data['disabled']),
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
                disabled($data['disabled']),
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

    public static function getLocalScriptUrl(string $scriptFileName, bool $frontScript = true): string
    {
        global $comfino_payment_gateway;

        $scriptDirectory = ($frontScript ? 'front' : 'admin');

        if (ConfigManager::isDevEnv() && ConfigManager::useUnminifiedScripts()) {
            $scriptFileName = str_replace('.min.js', '.js', $scriptFileName);

            if (!file_exists($comfino_payment_gateway->plugin_abspath() . "/resources/js/$scriptDirectory/$scriptFileName")) {
                $scriptFileName = str_replace('.js', '.min.js', $scriptFileName);
            }
        } elseif (strpos($scriptFileName, '.min.') === false) {
            $scriptFileName = str_replace('.js', '.min.js', $scriptFileName);
        }

        return $comfino_payment_gateway->plugin_url() . "/resources/js/$scriptDirectory/$scriptFileName";
    }

    public static function getExternalResourcesBaseUrl(): string
    {
        if (ConfigManager::isDevEnv() && getenv('COMFINO_DEV_STATIC_RESOURCES_BASE_URL')) {
            return sanitize_url(wp_unslash(getenv('COMFINO_DEV_STATIC_RESOURCES_BASE_URL')));
        }

        return ConfigManager::isSandboxMode() ? 'https://widget.craty.pl' : 'https://widget.comfino.pl';
    }

    public static function getExternalScriptUrl(string $scriptFileName): string
    {
        if (empty($scriptFileName)) {
            return '';
        }

        if (ConfigManager::isDevEnv() && ConfigManager::useUnminifiedScripts()) {
            $scriptFileName = str_replace('.min.js', '.js', $scriptFileName);
        } elseif (strpos($scriptFileName, '.min.') === false) {
            $scriptFileName = str_replace('.js', '.min.js', $scriptFileName);
        }

        if (ConfigManager::isSandboxMode()) {
            $scriptPath = trim(ConfigManager::getConfigurationValue('COMFINO_JS_DEV_PATH'), '/');

            if (strpos($scriptPath, '..') !== false) {
                $scriptPath = trim(ConfigManager::getDefaultValue('js_dev_path'), '/');
            }
        } else {
            $scriptPath = trim(ConfigManager::getConfigurationValue('COMFINO_JS_PROD_PATH'), '/');

            if (strpos($scriptPath, '..') !== false) {
                $scriptPath = trim(ConfigManager::getDefaultValue('js_prod_path'), '/');
            }
        }

        if (!empty($scriptPath)) {
            $scriptPath = "/$scriptPath";
        }

        return sanitize_url(wp_unslash(self::getExternalResourcesBaseUrl() . "$scriptPath/$scriptFileName"));
    }

    public static function getExternalStyleUrl(string $styleFileName): string
    {
        if (empty($styleFileName)) {
            return '';
        }

        if (ConfigManager::isSandboxMode()) {
            $stylePath = trim(ConfigManager::getConfigurationValue('COMFINO_CSS_DEV_PATH', 'css'), '/');

            if (strpos($stylePath, '..') !== false) {
                $stylePath = trim(ConfigManager::getDefaultValue('css_dev_path'), '/');
            }
        } else {
            $stylePath = trim(ConfigManager::getConfigurationValue('COMFINO_CSS_PROD_PATH', 'css'), '/');

            if (strpos($stylePath, '..') !== false) {
                $stylePath = trim(ConfigManager::getDefaultValue('css_prod_path'), '/');
            }
        }

        if (!empty($stylePath)) {
            $stylePath = "/$stylePath";
        }

        return sanitize_url(wp_unslash(self::getExternalResourcesBaseUrl() . "$stylePath/$styleFileName"));
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
    public static function includeExternalScripts(array $scripts, array $dependencies = [], bool $inFooter = true, $version = null): array
    {
        $scriptIds = [];

        foreach ($scripts as $scriptName) {
            $scriptId = 'comfino-script-' . str_replace('.', '-', strtolower(pathinfo($scriptName, PATHINFO_FILENAME)));
            $scriptIds[] = $scriptId;

            wp_enqueue_script(
                $scriptId,
                self::getExternalScriptUrl($scriptName),
                $dependencies[$scriptName] ?? [],
                $version,
                ['in_footer' => $inFooter]
            );
        }

        return $scriptIds;
    }

    /**
     * @param string[] $styles
     * @param string[][] $dependencies
     *
     * @return string[]
     */
    public static function includeExternalStyles(array $styles, array $dependencies = [], $version = null): array
    {
        $styleIds = [];

        foreach ($styles as $styleName) {
            $styleId = 'comfino-style-' . str_replace('.', '-', strtolower(pathinfo($styleName, PATHINFO_FILENAME)));
            $styleIds[] = $styleId;

            wp_enqueue_style($styleId, self::getExternalStyleUrl($styleName), $dependencies[$styleName] ?? [], $version);
        }

        return $styleIds;
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
     * @param string[] $scripts
     * @param string[][] $dependencies
     *
     * @return string[]
     */
    public static function registerExternalScripts(array $scripts, array $dependencies = [], bool $inFooter = true, $version = null): array
    {
        $scriptIds = [];

        foreach ($scripts as $scriptName) {
            $scriptId = 'comfino-script-' . str_replace('.', '-', strtolower(pathinfo($scriptName, PATHINFO_FILENAME)));
            $scriptIds[] = $scriptId;

            wp_register_script(
                $scriptId,
                self::getExternalScriptUrl($scriptName),
                $dependencies[$scriptName] ?? [],
                $version,
                ['in_footer' => $inFooter]
            );
        }

        return $scriptIds;
    }

    /**
     * @param string[] $styles
     * @param string[][] $dependencies
     *
     * @return string[]
     */
    public static function registerExternalStyles(array $styles, array $dependencies = [], $version = null): array
    {
        $styleIds = [];

        foreach ($styles as $styleName) {
            $styleId = 'comfino-style-' . str_replace('.', '-', strtolower(pathinfo($styleName, PATHINFO_FILENAME)));
            $styleIds[] = $styleId;

            wp_register_style($styleId, self::getExternalStyleUrl($styleName), $dependencies[$styleName] ?? [], $version);
        }

        return $styleIds;
    }

    public static function renderWidgetInitCode(?int $productId): string
    {
        $serializer = new JsonSerializer();

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
                            'OFFER_TYPES',
                            'EMBED_METHOD',
                            'SHOW_PROVIDER_LOGOS',
                        ],
                        array_map(
                            static function ($optionValue) use ($serializer) {
                                return is_array($optionValue) ? $serializer->serialize($optionValue) : $optionValue;
                            },
                            ConfigManager::getConfigurationValues(
                                'widget_settings',
                                [
                                    'COMFINO_WIDGET_KEY',
                                    'COMFINO_WIDGET_PRICE_SELECTOR',
                                    'COMFINO_WIDGET_TARGET_SELECTOR',
                                    'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR',
                                    'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL',
                                    'COMFINO_WIDGET_TYPE',
                                    'COMFINO_WIDGET_OFFER_TYPES',
                                    'COMFINO_WIDGET_EMBED_METHOD',
                                    'COMFINO_WIDGET_SHOW_PROVIDER_LOGOS',
                                ]
                            )
                        )
                    ),
                    ConfigManager::getWidgetVariables($productId)
                )
            );
        } catch (\Throwable $e) {
            ErrorLogger::sendError(
                $e,
                'Widget script endpoint',
                $e->getCode(),
                $e->getMessage(),
                $e instanceof HttpErrorExceptionInterface ? $e->getUrl() : null,
                $e instanceof HttpErrorExceptionInterface ? $e->getRequestBody() : null,
                $e instanceof HttpErrorExceptionInterface ? $e->getResponseBody() : null,
                $e->getTraceAsString()
            );
        }

        return '';
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
                'input' => ['id' => [], 'name' => [], 'value' => [], 'class' => [], 'style' => [], 'title' => [], 'placeholder' => [], 'type' => [], 'checked' => [], 'readonly' => [], 'disabled' => [], 'required' => []],
                'select' => ['id' => [], 'name' => [], 'multiple' => [], 'disabled' => [], 'required' => []],
                'option' => ['value' => [], 'selected' => [], 'label' => [], 'disabled' => []],
            ],
            self::getAllowedScriptHtml(),
            self::getAllowedStyleHtml()
        );
    }
}
