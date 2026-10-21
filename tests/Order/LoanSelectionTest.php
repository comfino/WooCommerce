<?php

namespace Comfino\Tests\Order;

use Comfino\Configuration\ConfigManager;
use Comfino\Main;
use Comfino\Order\LoanSelection;
use Comfino\Order\OrderManager;
use Comfino\PluginShared\CacheManager;
use Comfino\Tests\Stub\StubApiClient;
use Comfino\Tests\TestState;

/**
 * Loan selection allowlist, safe default product and the summary warning report.
 */
class LoanSelectionTest extends \PHPUnit_Framework_TestCase
{
    const PAYWALL_TYPES = ['PAY_LATER' => 'Pay later', 'INSTALLMENTS_ZERO_PERCENT' => '0% installments', 'BLIK' => 'BLIK'];

    /** @var StubApiClient */
    private $apiClient;

    public function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REQUEST_SCHEME'] = 'https';
        $_SERVER['HTTP_HOST'] = 'comfino-wc-store.test';

        Main::setPluginDirectory(__DIR__ . '/../..');
        Main::setPluginFile(__DIR__ . '/../../comfino-payment-gateway.php');

        TestState::reset();

        $this->apiClient = new StubApiClient();
        TestState::setApiClient($this->apiClient);

    }

    public function tearDown(): void
    {
        TestState::reset();

        parent::tearDown();
    }

    public function testValidateAcceptsAllowedTypeAndTerm(): void
    {
        $this->assertSame(['PAY_LATER', 1], LoanSelection::validate('PAY_LATER', 1, ['PAY_LATER']));
        $this->assertSame(['INSTALLMENTS_ZERO_PERCENT', 12], LoanSelection::validate(' INSTALLMENTS_ZERO_PERCENT ', 12, ['INSTALLMENTS_ZERO_PERCENT']));
    }

    public function testValidateRejectsDisabledUnknownAndMalformedTypes(): void
    {
        $this->assertNull(LoanSelection::validate('PAY_LATER', 1, ['INSTALLMENTS_ZERO_PERCENT']), 'disabled type');
        $this->assertNull(LoanSelection::validate('NOT_A_PRODUCT', 1, ['NOT_A_PRODUCT']), 'unknown type');
        $this->assertNull(LoanSelection::validate('pay_later', 1, ['pay_later']), 'malformed type');
        $this->assertNull(LoanSelection::validate('', 1, ['PAY_LATER']), 'missing type');
        $this->assertNull(LoanSelection::validate('undefined', 1, ['PAY_LATER']), 'JS "undefined"');
    }

    public function testValidateEnforcesTermBounds(): void
    {
        $this->assertNull(LoanSelection::validate('PAY_LATER', 0, ['PAY_LATER']));
        $this->assertNull(LoanSelection::validate('PAY_LATER', 121, ['PAY_LATER']), 'hard sanity cap');

        $config = [['type' => 'INSTALLMENTS_ZERO_PERCENT', 'minTerm' => 3, 'maxTerm' => 24, 'terms' => [3, 6, 12, 24]]];

        $this->assertSame(['INSTALLMENTS_ZERO_PERCENT', 6], LoanSelection::validate('INSTALLMENTS_ZERO_PERCENT', 6, ['INSTALLMENTS_ZERO_PERCENT'], $config));
        $this->assertNull(LoanSelection::validate('INSTALLMENTS_ZERO_PERCENT', 2, ['INSTALLMENTS_ZERO_PERCENT'], $config));
        $this->assertNull(LoanSelection::validate('INSTALLMENTS_ZERO_PERCENT', 36, ['INSTALLMENTS_ZERO_PERCENT'], $config));
        $this->assertNull(LoanSelection::validate('INSTALLMENTS_ZERO_PERCENT', 9, ['INSTALLMENTS_ZERO_PERCENT'], $config));
    }

    public function testAllowedTypesFallBackToPaywallListWithoutFilters(): void
    {
        $this->primeProductTypes(self::PAYWALL_TYPES);

        $this->assertSame(array_keys(self::PAYWALL_TYPES), LoanSelection::getAllowedProductTypes($this->getShopCart()));
    }

    public function testFailedProductTypeLookupRejectsEverySelection(): void
    {
        // No filters and the product type lookup fails: null from getAllowedProductTypes() must not mean "allow all".
        ConfigManager::getInstance()->setConfigurationValue('COMFINO_API_KEY', 'TEST_API_KEY_0123456789');
        $this->apiClient->productTypesException = new \RuntimeException('API down');

        $allowedProductTypes = LoanSelection::getAllowedProductTypes($this->getShopCart());

        $this->assertSame([], $allowedProductTypes);
        $this->assertNull(LoanSelection::validate('PAY_LATER', 1, $allowedProductTypes));
    }

    public function testSafeDefaultSkipsInstantPaymentsAndBlik(): void
    {
        $products = [(object) ['type' => 'INSTANT_PAYMENTS'], (object) ['type' => 'BLIK'], (object) ['type' => 'PAY_LATER', 'loanTerm' => 1]];

        $this->assertSame($products[2], LoanSelection::selectDefaultProduct($products));
        $this->assertNull(LoanSelection::selectDefaultProduct(array_slice($products, 0, 2)));
        $this->assertNull(LoanSelection::selectDefaultProduct([]));
    }

    public function testWarningSummaryIsRateLimitedPerCode(): void
    {
        $first = LoanSelection::recordOccurrence(LoanSelection::CODE_DEFAULTED);

        $this->assertSame(1, $first['count'], 'The first occurrence reports at once.');
        $this->assertNull(LoanSelection::recordOccurrence(LoanSelection::CODE_DEFAULTED));
        $this->assertNull(LoanSelection::recordOccurrence(LoanSelection::CODE_DEFAULTED));
        $this->assertSame(1, LoanSelection::recordOccurrence('OTHER_CODE')['count'], 'Codes are counted separately.');

        // Move the window start back by a day: the next occurrence sends the accumulated count, then resets.
        $state = get_transient('comfino_loan_selection_warning_' . strtolower(LoanSelection::CODE_DEFAULTED));
        $state['since'] -= 86400;
        set_transient('comfino_loan_selection_warning_' . strtolower(LoanSelection::CODE_DEFAULTED), $state);

        $summary = LoanSelection::recordOccurrence(LoanSelection::CODE_DEFAULTED);

        $this->assertSame(3, $summary['count']);
        $this->assertSame($state['since'], $summary['since']);
        $this->assertNull(LoanSelection::recordOccurrence(LoanSelection::CODE_DEFAULTED));
    }

    public function testWarningReportDoesNotThrowAndCarriesNoIds(): void
    {
        LoanSelection::reportNotPosted(LoanSelection::CODE_DEFAULTED, ['comfino_loan_term'], true);

        // The shared sendError() drops messages looking like PHP errors ("Error ... in") - the report must not.
        $message = sprintf(
            'Orders submitted without the selected financing offer: %d since %s (missing in the latest: %s), checkout %s, %s.',
            1,
            gmdate('Y-m-d H:i:s') . ' UTC',
            'comfino_loan_term',
            'blocks',
            'safe default financing offer used'
        );

        $this->assertSame(0, preg_match('/Error .*in |Exception .*in /', $message));
    }

    private function primeProductTypes(array $productTypes): void
    {
        CacheManager::set('product_types.paywall.' . Main::getShopLanguage(), $productTypes);
    }

    private function getShopCart(): \Comfino\Common\Shop\Cart
    {
        return OrderManager::getShopCart(new \WC_Cart());
    }
}
