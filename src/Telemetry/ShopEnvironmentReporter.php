<?php

namespace Comfino\Telemetry;

use Comfino\Api\ApiClient;
use Comfino\DebugLogger;
use Comfino\Frontend\WooCommerceShopEnvironmentBuilder;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fire-and-forget service that reports the full shop environment to the Comfino API.
 *
 * Mirrors the Magento reference (Comfino\ComfinoGateway\Model\Telemetry\ShopEnvironmentReporter): builds the report
 * via WooCommerceShopEnvironmentBuilder and posts it through the shared API client's reportShopEnvironment().
 * Triggered on payment-gateway settings save. Any failure is logged and swallowed — it must never impact checkout,
 * paywall, or widget functionality.
 */
final class ShopEnvironmentReporter
{
    /**
     * Builds and sends the current shop environment report to the Comfino API.
     *
     * @return bool True if the report was accepted, false on any failure.
     */
    public static function report(): bool
    {
        try {
            $report = WooCommerceShopEnvironmentBuilder::createDefault()
                ->buildForBackendReport(self::resolveTestProductUrl(), self::buildMeta());

            $result = ApiClient::getInstance()->reportShopEnvironment($report);

            DebugLogger::logEvent(
                '[SHOP_ENVIRONMENT]',
                'ShopEnvironmentReporter::report: ' . ($result ? 'accepted' : 'rejected by API')
            );

            return $result;
        } catch (\Throwable $e) {
            DebugLogger::logEvent(
                '[SHOP_ENVIRONMENT]',
                'ShopEnvironmentReporter::report: failed',
                ['exceptionMessage' => $e->getMessage()]
            );

            return false;
        }
    }

    /**
     * Builds the current shop environment report as an array, for on-demand exposure via the configuration endpoint.
     *
     * @return array<string, mixed>|null The report array, or null on failure.
     */
    public static function getReportArray(): ?array
    {
        try {
            return WooCommerceShopEnvironmentBuilder::createDefault()->buildReportArray(
                self::resolveTestProductUrl(),
                self::buildMeta()
            );
        } catch (\Throwable $e) {
            DebugLogger::logEvent(
                '[SHOP_ENVIRONMENT]',
                'ShopEnvironmentReporter::getReportArray: failed',
                ['exceptionMessage' => $e->getMessage()]
            );

            return null;
        }
    }

    /**
     * Resolves the URL of the first published product so the API may crawl it for selector auto-detection.
     *
     * @return string|null Product permalink, or null when no published product exists or resolution fails.
     */
    private static function resolveTestProductUrl(): ?string
    {
        try {
            if (!function_exists('wc_get_products')) {
                return null;
            }

            $productIds = wc_get_products([
                'status' => 'publish',
                'limit' => 1,
                'orderby' => 'ID',
                'order' => 'ASC',
                'return' => 'ids',
            ]);

            if (empty($productIds)) {
                return null;
            }

            $permalink = get_permalink((int) $productIds[0]);

            return $permalink !== false ? $permalink : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Builds the custom metadata passed into the shop environment report: checkout type, installed plugins known to
     * disrupt standard WooCommerce checkout/order behavior (analogous to the PrestaShop reporter's 'opc_modules'),
     * HPOS status, installed caching plugins, installed URL-hiding/security-hardening plugins, and installed
     * product-options/dynamic-pricing plugins.
     *
     * The API's 'meta' field only accepts a flat map of scalar values (@see ReportShopEnvironment::sanitizeMeta() in
     * the shop-plugins-shared library) - any nested array value is silently dropped before the request is sent. All
     * detector methods below return their natural (nested) shape for internal readability; this method is the single
     * place responsible for flattening that shape into scalars before it's returned.
     *
     * @return array<string, bool|int|string>
     */
    private static function buildMeta(): array
    {
        $checkoutType = self::detectCheckoutType();
        $checkoutPlugins = self::detectCheckoutPlugins();
        $orderNumberPlugins = self::detectOrderNumberPlugins();
        $cachePlugins = self::detectCachePlugins();
        $urlHidingPlugins = self::detectUrlHidingPlugins();
        $productOptionsPlugins = self::detectProductOptionsPlugins();
        $hposStatus = self::detectHposStatus();

        return [
            'checkout_type_blocks' => $checkoutType['blocks'],
            'checkout_type_shortcode' => $checkoutType['shortcode'],
            'checkout_plugins_count' => count($checkoutPlugins),
            'checkout_plugins_names' => self::formatDetectedPlugins($checkoutPlugins),
            'order_number_plugins_count' => count($orderNumberPlugins),
            'order_number_plugins_names' => self::formatDetectedPlugins($orderNumberPlugins),
            'cache_plugins_count' => count($cachePlugins),
            'cache_plugins_names' => self::formatDetectedPlugins($cachePlugins),
            'url_hiding_plugins_count' => count($urlHidingPlugins),
            'url_hiding_plugins_names' => self::formatDetectedPlugins($urlHidingPlugins),
            'product_options_plugins_count' => count($productOptionsPlugins),
            'product_options_plugins_names' => self::formatDetectedPlugins($productOptionsPlugins),
            'hpos_enabled' => $hposStatus['enabled'],
            'hpos_data_sync_enabled' => $hposStatus['data_sync_enabled'],
        ];
    }

    /**
     * Formats detected plugins as "Name:Version" (or just "Name" when no version was resolved), joined with commas
     * (no spaces), for the flattened scalar-only 'meta' field (@see buildMeta()).
     *
     * @param array<int, array<string, mixed>> $plugins
     */
    private static function formatDetectedPlugins(array $plugins): string
    {
        $formatted = [];

        foreach ($plugins as $plugin) {
            $formatted[] = empty($plugin['version']) ? $plugin['name'] : $plugin['name'] . ':' . $plugin['version'];
        }

        return implode(',', $formatted);
    }

    /**
     * Detects the shop's HPOS (High-Performance Order Storage) status: whether the custom orders tables are the
     * authoritative order storage, and whether background data sync with the legacy post-based storage is enabled.
     *
     * Uses the same `OrderUtil::custom_orders_table_usage_is_enabled()` check already relied on elsewhere in the
     * plugin (@see OrderManager::supportsMetaQuery()) for the 'enabled' flag; the sync option is read directly since
     * it doesn't require instantiating WooCommerce's internal DataSynchronizer service.
     *
     * @return array{enabled: bool, data_sync_enabled: bool}
     */
    private static function detectHposStatus(): array
    {
        $hposStatus = ['enabled' => false, 'data_sync_enabled' => false];

        try {
            if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
                $hposStatus['enabled'] = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
            }

            $hposStatus['data_sync_enabled'] = get_option('woocommerce_custom_orders_table_data_sync_enabled') === 'yes';
        } catch (\Throwable $e) {
            // Keep defaults on any failure.
        }

        return $hposStatus;
    }

    /**
     * Detects whether the shop's checkout page uses the WooCommerce Blocks checkout (the `woocommerce/checkout`
     * Gutenberg block) or the legacy shortcode-based checkout (`[woocommerce_checkout]`), by inspecting the content
     * of the page registered as WooCommerce's checkout page.
     *
     * Both flags can be true (a page can contain both the block and the shortcode, though WooCommerce only renders
     * one) or both false (no checkout page configured, or its content could not be inspected) - this is diagnostic
     * data only and doesn't drive any plugin behavior.
     *
     * @return array{blocks: bool, shortcode: bool}
     */
    private static function detectCheckoutType(): array
    {
        $checkoutType = ['blocks' => false, 'shortcode' => false];

        try {
            if (!function_exists('wc_get_page_id')) {
                return $checkoutType;
            }

            $checkoutPageId = (int) wc_get_page_id('checkout');

            if ($checkoutPageId <= 0) {
                return $checkoutType;
            }

            $checkoutPage = get_post($checkoutPageId);

            if ($checkoutPage === null) {
                return $checkoutType;
            }

            $content = $checkoutPage->post_content;

            $checkoutType['blocks'] = function_exists('has_block') && has_block('woocommerce/checkout', $content);
            $checkoutType['shortcode'] = function_exists('has_shortcode') && has_shortcode($content, 'woocommerce_checkout');
        } catch (\Throwable $e) {
            // Keep defaults on any failure.
        }

        return $checkoutType;
    }

    /**
     * Detects well-known WooCommerce checkout-replacing/checkout-modifying plugins that can interfere with standard
     * order field persistence (e.g., FunnelKit Checkout has been observed saving billing address fields after the
     * payment gateway's process_payment() already ran, causing false "missing city/postal code" validation errors).
     *
     * Detection is best-effort and matches by the plugin's main file path; plugins with a non-standard installation
     * layout (or paid variants distributed outside WordPress.org) simply won't be detected - this data is diagnostic
     * only and feeds into the shop environment report's 'meta' field.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function detectCheckoutPlugins(): array
    {
        $knownCheckoutPlugins = [
            /* FunnelKit Checkout / Funnel Builder for WooCommerce (formerly WooFunnels) - replaces the native checkout
               with its own AJAX flow and order-creation pipeline. */
            'funnel-builder/funnel-builder.php' => 'FunnelKit Checkout (Funnel Builder for WooCommerce)',
            'cartflows/cartflows.php' => 'CartFlows',
            'checkoutwc-lite/checkoutwc-lite.php' => 'CheckoutWC Lite',
            'woo-checkout-field-editor-pro/woo-checkout-field-editor-pro.php' => 'Checkout Field Editor for WooCommerce (ThemeHigh)',
            'fluid-checkout/fluid-checkout.php' => 'Fluid Checkout for WooCommerce',
        ];

        return self::detectPluginsByFile($knownCheckoutPlugins);
    }

    /**
     * Detects well-known WordPress page/object caching plugins, since aggressive full-page caching can serve stale
     * paywall/widget markup or cache the checkout page itself if not properly excluded by the merchant.
     *
     * Detection is best-effort and matches by the plugin's main file path; plugins with a non-standard installation
     * layout (or paid variants distributed outside WordPress.org) simply won't be detected - this data is diagnostic
     * only and feeds into the shop environment report's 'meta' field.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function detectCachePlugins(): array
    {
        $knownCachePlugins = [
            'wp-super-cache/wp-cache.php' => 'WP Super Cache',
            'w3-total-cache/w3-total-cache.php' => 'W3 Total Cache',
            'wp-rocket/wp-rocket.php' => 'WP Rocket',
            'wp-fastest-cache/wpFastestCache.php' => 'WP Fastest Cache',
            'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache',
            'cache-enabler/cache-enabler.php' => 'Cache Enabler',
            'wp-optimize/wp-optimize.php' => 'WP-Optimize',
            'hummingbird-performance/wp-hummingbird.php' => 'Hummingbird (WPMU DEV)',
            'sg-cachepress/sg-cachepress.php' => 'SG Optimizer (SiteGround)',
            'breeze/breeze.php' => 'Breeze (Cloudways)',
            'swift-performance-lite/performance.php' => 'Swift Performance Lite',
            'swift-performance/performance.php' => 'Swift Performance',
            'comet-cache/comet-cache.php' => 'Comet Cache',
            'simple-cache/simple-cache.php' => 'Simple Cache',
        ];

        return self::detectPluginsByFile($knownCachePlugins);
    }

    /**
     * Detects well-known WordPress URL-hiding/security-hardening plugins that rewrite or block default WordPress
     * URLs (plugin asset paths, the REST API base) - e.g., WP Hide & Security Enhancer can rename the plugin's own
     * asset URLs and optionally disable/clean the REST API (wp-json) entirely, which could break the paywall widget's
     * script loading or Comfino's webhook callback endpoint if not explicitly excluded by the merchant.
     *
     * Detection is best-effort and matches by the plugin's main file path; this data is diagnostic only and feeds
     * into the shop environment report's 'meta' field.
     *
     * Main file paths below are verified against the plugin's WordPress.org SVN trunk except where noted -
     * 'wp-hide-security-enhancer-pro' and 'hide_my_wp' are paid/CodeCanyon-distributed variants with no public
     * repository to verify against, so their technical names are best-effort guesses.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function detectUrlHidingPlugins(): array
    {
        $knownUrlHidingPlugins = [
            'wp-hide-security-enhancer/wp-hide.php' => 'WP Hide & Security Enhancer',
            'wp-hide-security-enhancer-pro/wp-hide.php' => 'WP Hide & Security Enhancer Pro', // unverified (paid variant)
            'hide-my-wp/index.php' => 'WP Ghost (Hide My WP Ghost)',
            'hide_my_wp/hide-my-wp.php' => 'Hide My WP Premium (CodeCanyon)', // unverified (paid variant)
            'wps-hide-login/wps-hide-login.php' => 'WPS Hide Login',
            'easy-hide-login/wp-hide-login.php' => 'Easy Hide Login',
            'wp-cerber/wp-cerber.php' => 'WP Cerber Security',
            'better-wp-security/better-wp-security.php' => 'Solid Security (formerly iThemes Security)',
            'all-in-one-wp-security-and-firewall/wp-security.php' => 'All-In-One Security (AIOS)',
        ];

        return self::detectPluginsByFile($knownUrlHidingPlugins);
    }

    /**
     * Detects well-known WooCommerce product-options/custom-fields and dynamic-pricing plugins. These can alter a
     * product's final price or line-item structure outside WooCommerce's standard cart/order pipeline (custom
     * per-item price add-ons, quantity-based discounts, formula-based pricing), which is diagnostically relevant
     * since it affects the order total reported to the Comfino API for installment/paywall calculation.
     *
     * Detection is best-effort and matches by the plugin's main file path; this data is diagnostic only and feeds
     * into the shop environment report's 'meta' field.
     *
     * The main file paths below are verified against the plugin's WordPress.org SVN trunk (free plugins) or a public
     * code mirror (paid plugins not distributed via WordPress.org, cross-checked since mirrors of paid code can be
     * stale or unofficial) except where noted - 'uni-woo-custom-product-options-premium' and
     * 'advanced-product-fields-for-woocommerce-pro' have no public source to verify against, so their technical
     * names are best-effort guesses.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function detectProductOptionsPlugins(): array
    {
        $knownProductOptionsPlugins = [
            'uni-woo-custom-product-options/uni-cpo.php' => 'Uni Woo Custom Product Options',
            'uni-woo-custom-product-options-premium/uni-cpo.php' => 'Uni Woo Custom Product Options Premium', // unverified (paid variant)
            'woo-extra-product-options/woo-extra-product-options.php' => 'Extra Product Options for WooCommerce (ThemeHigh)',
            'advanced-product-fields-for-woocommerce/advanced-product-fields-for-woocommerce.php' => 'Advanced Product Fields for WooCommerce (Studio Wombat)',
            'advanced-product-fields-for-woocommerce-pro/advanced-product-fields-for-woocommerce-pro.php' => 'Advanced Product Fields for WooCommerce Pro (Studio Wombat)', // unverified (paid variant)
            'flexible-product-fields/flexible-product-fields.php' => 'Flexible Product Fields (WP Desk)',
            'woo-custom-product-addons/start.php' => 'Product Addons for WooCommerce (Acowebs)',
            'woocommerce-product-addons/woocommerce-product-addons.php' => 'WooCommerce Product Add-Ons (Official)',
            'woocommerce-tm-extra-product-options/tm-woo-extra-product-options.php' => 'Extra Product Options & Add-Ons for WooCommerce (ThemeComplete)',
            'woocommerce-dynamic-pricing-and-discounts/wc-dynamic-pricing-and-discounts.php' => 'WooCommerce Dynamic Pricing & Discounts (RightPress)',
        ];

        return self::detectPluginsByFile($knownProductOptionsPlugins);
    }

    /**
     * Detects sequential/custom order number plugins already known to this module.
     *
     * Mirrors the detection signatures used by OrderManager::loadOrderByNumber() to locate orders by custom
     * number - kept as a separate, simpler existence check here (rather than reusing that method) since this
     * only needs to report presence, not resolve an order.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function detectOrderNumberPlugins(): array
    {
        $detected = [];

        if (function_exists('wc_sequential_order_numbers')) {
            $detected[] = ['name' => 'Sequential Order Numbers for WooCommerce (SkyVerge)', 'version' => null, 'active' => true];
        }

        if (class_exists('Wt_Advanced_Order_Number')) {
            $detected[] = ['name' => 'Sequential Order Number for WooCommerce (WebToffee)', 'version' => null, 'active' => true];
        }

        if (class_exists('YITH_WooCommerce_Sequential_Order_Number') || class_exists('YITH_Sequential_Order_Number')) {
            $detected[] = ['name' => 'YITH WooCommerce Sequential Order Number', 'version' => null, 'active' => true];
        }

        if (class_exists('Alg_WC_Custom_Order_Numbers') || function_exists('alg_wc_custom_order_numbers')) {
            $detected[] = ['name' => 'Custom Order Numbers for WooCommerce (Algoritmika/Booster)', 'version' => null, 'active' => true];
        }

        if (class_exists('Tyche_Softwares_Order_Numbers') || function_exists('tyche_order_number')) {
            $detected[] = ['name' => 'Custom Order Numbers (Tyche Softwares)', 'version' => null, 'active' => true];
        }

        return $detected;
    }

    /**
     * Checks a map of [plugin main file => display name] against the active plugin list and reports each active match
     * along with its declared version, read from the plugin header.
     *
     * @param array<string, string> $knownPlugins Map of plugin main file path (relative to the plugins dir) to
     *                                            a human-readable display name
     *
     * @return array<int, array<string, mixed>>
     */
    private static function detectPluginsByFile(array $knownPlugins): array
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $detected = [];

        foreach ($knownPlugins as $pluginFile => $pluginName) {
            try {
                if (!is_plugin_active($pluginFile)) {
                    continue;
                }

                $pluginData = get_plugin_data(WP_PLUGIN_DIR . '/' . $pluginFile, false, false);

                $detected[] = [
                    'name' => $pluginName,
                    'version' => !empty($pluginData['Version']) ? $pluginData['Version'] : null,
                    'active' => true,
                ];
            } catch (\Throwable $e) {
                // Skip plugins that can't be inspected.
            }
        }

        return $detected;
    }
}
