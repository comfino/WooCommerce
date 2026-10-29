<?php

namespace Comfino\Order;

use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Common\Shop\Cart;
use Comfino\Configuration\SettingsManager;
use Comfino\DebugLogger;
use Comfino\ErrorLogger;
use Comfino\Extended\Api\Dto\Plugin\ErrorCategory;
use Comfino\Extended\Api\Dto\Plugin\OperationContext;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validation of the financing product (type and term) a shopper picked in the paywall, the safe default product used
 * when the checkout posts no valid selection, and the summary report of such checkouts.
 */
final class LoanSelection
{
    public const MAX_TERM = 120;
    public const CODE_DEFAULTED = 'LOAN_SELECTION_NOT_POSTED_DEFAULTED';

    private const REPORT_INTERVAL = 86400;
    private const STATE_TTL = 2592000;
    private const STATE_TRANSIENT_PREFIX = 'comfino_loan_selection_warning_';
    private const EXCLUDED_DEFAULT_TYPES = ['INSTANT_PAYMENTS', 'BLIK'];

    /**
     * Product types a selection may use for this cart. getAllowedProductTypes() returns null both for "no filter" and
     * for a failed product type lookup, so null falls back to the full paywall type list; an empty result means that
     * nothing can be validated and every selection is rejected (fail closed).
     *
     * @return string[]
     */
    public static function getAllowedProductTypes(Cart $shopCart): array
    {
        try {
            $allowedProductTypes = SettingsManager::getAllowedProductTypes(
                ProductTypesListTypeEnum::LIST_TYPE_PAYWALL,
                $shopCart
            );
        } catch (\Throwable $e) {
            $allowedProductTypes = null;
        }

        if ($allowedProductTypes === null) {
            return SettingsManager::getProductTypesStrings(ProductTypesListTypeEnum::LIST_TYPE_PAYWALL);
        }

        return array_map('strval', $allowedProductTypes);
    }

    /**
     * Validates a selection against the loan type enum, the allowed product types, the per-type term bounds of the
     * allowed products configuration and a hard 1..MAX_TERM sanity cap.
     *
     * @param string[] $allowedProductTypes
     * @param array[]|null $allowedProductsConfig Normalized entries of SettingsManager::getAllowedProductsConfigForFrontend()
     *
     * @return array{0: string, 1: int}|null [type, term] or null when the selection is not acceptable
     */
    public static function validate(
        string $loanType,
        int $loanTerm,
        array $allowedProductTypes,
        ?array $allowedProductsConfig = null
    ): ?array {
        $loanType = trim($loanType);

        if ($loanType === '' || $loanTerm < 1 || $loanTerm > self::MAX_TERM || preg_match('/^[A-Z_]{1,40}$/', $loanType) !== 1) {
            return null;
        }

        try {
            new LoanTypeEnum($loanType);
        } catch (\Throwable $e) {
            return null;
        }

        if (!in_array($loanType, $allowedProductTypes, true)) {
            return null;
        }

        foreach ($allowedProductsConfig ?? [] as $entry) {
            if (($entry['type'] ?? null) !== $loanType) {
                continue;
            }

            if ((isset($entry['minTerm']) && $loanTerm < $entry['minTerm']) ||
                (isset($entry['maxTerm']) && $loanTerm > $entry['maxTerm']) ||
                (!empty($entry['terms']) && !in_array($loanTerm, $entry['terms'], true))
            ) {
                return null;
            }
        }

        return [$loanType, $loanTerm];
    }

    /**
     * Picks the safe default financial product when the shopper's selection is unavailable: the first offer, skipping
     * INSTANT_PAYMENTS and BLIK. INSTANT_PAYMENTS starts a completely different payment path that cannot be changed
     * later, and BLIK is excluded as a default as well. When only excluded types are offered, nothing is chosen.
     *
     * @param object[] $financialProducts Offers in the order returned by the financial products endpoint.
     *
     * @return object|null
     */
    public static function selectDefaultProduct(array $financialProducts)
    {
        foreach ($financialProducts as $financialProduct) {
            if (!in_array((string) $financialProduct->type, self::EXCLUDED_DEFAULT_TYPES, true)) {
                return $financialProduct;
            }
        }

        return null;
    }

    /**
     * Counts one occurrence of a code and tells whether a report is due: the first occurrence is due at once, then one
     * report per interval. The counter keeps accumulating between reports, so a due report carries the number of
     * occurrences since the previous report and its start time.
     *
     * @param string $code One of the CODE_* constants.
     *
     * @return array{count: int, since: int}|null Summary to report, or null when the interval has not elapsed yet.
     */
    public static function recordOccurrence(string $code): ?array
    {
        $transientName = self::STATE_TRANSIENT_PREFIX . strtolower($code);
        $now = time();
        $state = get_transient($transientName);

        if (!is_array($state) || !isset($state['since'], $state['count'])) {
            $state = ['since' => $now, 'count' => 0, 'reported' => false];
        }

        $state['count'] = (int) $state['count'] + 1;

        if (empty($state['reported']) || $now - (int) $state['since'] >= self::REPORT_INTERVAL) {
            $summary = ['count' => $state['count'], 'since' => (int) $state['since']];
            $state = ['since' => $now, 'count' => 0, 'reported' => true];
        } else {
            $summary = null;
        }

        set_transient($transientName, $state, self::STATE_TTL);

        return $summary;
    }

    /**
     * Logs the missing selection locally and reports it to ComfinoPay at most once per code per 24 hours. The report
     * is a summary without order or customer data: the number of occurrences since the previous report shows the
     * scale of the problem in the shop. It comes from the backend: only process_payment sees a POST that arrives
     * without the selection.
     *
     * @param string $code One of the CODE_* constants.
     * @param string[] $missingFields
     */
    public static function reportNotPosted(string $code, array $missingFields, bool $blocksCheckout): void
    {
        $checkoutKind = $blocksCheckout ? 'blocks' : 'classic';

        DebugLogger::logEvent('[PAYMENT]', 'Loan selection not posted', [
            'code' => $code,
            'missing_fields' => $missingFields,
            'checkout' => $checkoutKind,
        ]);

        if (($summary = self::recordOccurrence($code)) === null) {
            return;
        }

        try {
            ErrorLogger::sendWarning(
                ErrorCategory::ConfigurationError,
                OperationContext::OrderCreation,
                $code,
                sprintf(
                    'Orders submitted without the selected financing offer: %d since %s (missing in the latest: %s), checkout %s, %s.',
                    $summary['count'],
                    gmdate('Y-m-d H:i:s', $summary['since']) . ' UTC',
                    implode(', ', $missingFields),
                    $checkoutKind,
                    'safe default financing offer used'
                )
            );
        } catch (\Throwable $e) {
            // Reporting is best-effort.
        }
    }
}
