<?php

namespace Comfino\Configuration;

use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Common\Backend\ConfigurationManager;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\ErrorLogger;
use Comfino\Main;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validation of configuration values that can arrive from outside the admin form (the signed remote configuration
 * endpoint) and of values read back from storage. Invalid entries are dropped, never repaired.
 */
final class RemoteValueValidator
{
    public const CSS_URL_OPTIONS = [
        'COMFINO_PAYWALL_CUSTOM_CSS_URL',
        'COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL',
        'COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL',
    ];

    private const BOOL_VALUES = [true, false, 1, 0, '1', '0', 'yes', 'no', 'true', 'false', ''];

    /**
     * Keeps entries mapping a known ComfinoPay status to an existing WooCommerce order status (built-in or custom,
     * stored without the "wc-" prefix).
     *
     * @param mixed $statusMap
     *
     * @return array<string, string>
     */
    public static function filterStatusMap($statusMap): array
    {
        if (!is_array($statusMap)) {
            return [];
        }

        $orderStatuses = self::getOrderStatuses();

        return array_filter(
            $statusMap,
            static function ($shopStatus, $comfinoStatus) use ($orderStatuses): bool {
                if (!is_string($comfinoStatus) || !in_array($comfinoStatus, StatusManager::STATUSES, true) || !is_string($shopStatus)) {
                    return false;
                }

                if ($orderStatuses === null) {
                    // Order statuses not registered yet - accept a well-formed status slug only.
                    return preg_match('/^[a-z0-9_-]{1,40}$/', $shopStatus) === 1;
                }

                return in_array($shopStatus, $orderStatuses, true);
            },
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * @param mixed $statuses
     *
     * @return string[]
     */
    public static function filterStatusList($statuses): array
    {
        if (is_string($statuses)) {
            $statuses = array_map('trim', explode(',', $statuses));
        }

        if (!is_array($statuses)) {
            return [];
        }

        return array_values(array_unique(array_filter($statuses, static function ($status): bool {
            return is_string($status) && in_array($status, StatusManager::STATUSES, true);
        })));
    }

    /**
     * Whether the URL is acceptable as a custom CSS URL: an absolute https:// URL whose host (and port) equals the
     * shop host. Empty means "not set" and is valid. With the development environment variables active, plain HTTP is
     * allowed, as the local shops run without TLS.
     *
     * @param mixed $url
     */
    public static function isValidCssUrl($url): bool
    {
        if ($url === null || (is_string($url) && trim($url) === '')) {
            return true;
        }

        if (!is_string($url)) {
            return false;
        }

        $url = trim($url);

        if (preg_match('/[\x00-\x20"\'<>\\\\]/', $url) === 1) {
            return false;
        }

        $urlParts = wp_parse_url($url);

        if (!is_array($urlParts) || empty($urlParts['host']) || empty($urlParts['scheme'])) {
            return false;
        }

        $scheme = strtolower($urlParts['scheme']);

        if ($scheme !== 'https' && !($scheme === 'http' && ConfigManager::useDevEnvVars())) {
            return false;
        }

        $shopUrlParts = wp_parse_url(Main::getShopUrl());

        if (!is_array($shopUrlParts) || empty($shopUrlParts['host'])) {
            return false;
        }

        return self::normalizeHost($urlParts['host'], $urlParts['port'] ?? null) ===
            self::normalizeHost($shopUrlParts['host'], $shopUrlParts['port'] ?? null);
    }

    /**
     * @param mixed $value
     *
     * @return string|null The URL when valid and set, null otherwise
     */
    public static function getValidCssUrl($value): ?string
    {
        return is_string($value) && trim($value) !== '' && self::isValidCssUrl($value) ? trim($value) : null;
    }

    /**
     * Sanitizes option values about to be persisted by StorageAdapter::save(), the single write choke point of the
     * admin form and the remote configuration endpoint. Values are in their storage form (JSON options encoded by
     * ConfigurationManager::persist()), so the checks are applied on the decoded value. A value with any invalid part
     * is dropped as a whole, which keeps the stored value, and the rejection is logged locally.
     *
     * @param array<string, mixed> $options
     * @param array<string, int> $optionTypes Option type flags by option name
     *
     * @return array<string, mixed>
     */
    public static function sanitizeForStorage(array $options, array $optionTypes): array
    {
        foreach ($options as $name => $value) {
            switch ($name) {
                case 'COMFINO_STATUS_MAP':
                    $decoded = is_string($value) ? json_decode($value, true) : $value;
                    $valid = is_array($decoded) && count(self::filterStatusMap($decoded)) === count($decoded);

                    break;

                case 'COMFINO_IGNORED_STATUSES':
                case 'COMFINO_FORBIDDEN_STATUSES':
                    $list = self::toList($value);
                    $valid = $list !== null && count(self::filterStatusList($list)) === count($list);

                    break;

                case 'COMFINO_CHECKOUT_PRODUCT_TYPES':
                    $list = self::toList($value);
                    $valid = $list !== null && count(array_filter($list, static function ($productType): bool {
                        return is_string($productType) && in_array($productType, LoanTypeEnum::values(), true);
                    })) === count($list);

                    break;

                default:
                    if (in_array($name, self::CSS_URL_OPTIONS, true)) {
                        $valid = self::isValidCssUrl($value);
                    } elseif (($optionTypes[$name] ?? 0) & ConfigurationManager::OPT_VALUE_TYPE_BOOL) {
                        $valid = in_array($value, self::BOOL_VALUES, true);
                    } elseif (($optionTypes[$name] ?? 0) === ConfigurationManager::OPT_VALUE_TYPE_STRING) {
                        $valid = $value === null || is_scalar($value);
                    } else {
                        $valid = true;
                    }
            }

            if (!$valid) {
                unset($options[$name]);

                ErrorLogger::getLoggerInstance()->logError('[CONFIGURATION]', "Invalid value of $name rejected, stored value kept.");
            }
        }

        return $options;
    }

    /**
     * @param mixed $value Comma-separated string or array
     *
     * @return array|null
     */
    private static function toList($value): ?array
    {
        if (is_string($value)) {
            return array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));
        }

        if (!is_array($value)) {
            return null;
        }

        return array_values(array_filter($value, static function ($item): bool {
            return $item !== '' && $item !== null;
        }));
    }

    /**
     * @return string[]|null Order status slugs without the "wc-" prefix, null when WooCommerce is not loaded yet
     */
    private static function getOrderStatuses(): ?array
    {
        if (!function_exists('wc_get_order_statuses')) {
            return null;
        }

        return array_map(
            static function (string $orderStatus): string {
                return strpos($orderStatus, 'wc-') === 0 ? substr($orderStatus, 3) : $orderStatus;
            },
            array_keys(wc_get_order_statuses())
        );
    }

    private static function normalizeHost(string $host, $port): string
    {
        $host = strtolower($host);

        return $port !== null && !in_array((int) $port, [80, 443], true) ? "$host:$port" : $host;
    }
}
