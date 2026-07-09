<?php

/**
 * Comfino Payment Gateway for WooCommerce
 *
 * @package Comfino\Telemetry
 * @author Artur Kozubski <akozubski@comperia.pl>
 * @copyright Copyright (c) 2026 Comfino by Comperia.pl S.A.
 * @license https://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link https://github.com/comfino/woocommerce
 */

namespace Comfino\Telemetry;

use Comfino\Api\ApiClient;
use Comfino\DebugLogger;
use Comfino\Frontend\ThemeFamilyRules;
use Comfino\Frontend\WooCommerceShopEnvironmentBuilder;
use Comfino\Platform\WooCommercePlatformInfo;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fire-and-forget service that reports the full shop environment to the Comfino API.
 *
 * Mirrors the Magento reference (Comfino\ComfinoGateway\Model\Telemetry\ShopEnvironmentReporter): builds the report
 * via WooCommerceShopEnvironmentBuilder and posts it through the shared API client's reportShopEnvironment().
 * Triggered on payment-gateway settings save. Any failure is logged and swallowed — it must never impact checkout,
 * paywall or widget functionality.
 *
 * PHP 7.1 compatible (hand-written, not Rector-built).
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
            $builder = new WooCommerceShopEnvironmentBuilder(new WooCommercePlatformInfo(), self::createThemeRules());
            $report = $builder->buildForBackendReport(self::resolveTestProductUrl());

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
            $builder = new WooCommerceShopEnvironmentBuilder(new WooCommercePlatformInfo(), self::createThemeRules());

            return $builder->buildReportArray(self::resolveTestProductUrl());
        } catch (\Throwable $e) {
            DebugLogger::logEvent(
                '[SHOP_ENVIRONMENT]',
                'ShopEnvironmentReporter::getReportArray: failed',
                ['exceptionMessage' => $e->getMessage()]
            );

            return null;
        }
    }

    private static function createThemeRules(): ThemeFamilyRules
    {
        $rules = new ThemeFamilyRules();

        $rules->register('storefront', static function (array $themeChain): bool {
            foreach ($themeChain as $theme) {
                if (strpos($theme, 'storefront') !== false) {
                    return true;
                }
            }

            return false;
        });

        return $rules;
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
}