<?php

namespace Comfino\Tests\Order;

use Comfino\Common\Shop\Order\StatusApplicationContext;
use Comfino\Configuration\ConfigManager;
use Comfino\Main;
use Comfino\Order\ShopStatusManager;
use Comfino\Order\StatusAdapter;
use Comfino\Tests\Stub\StubApiClient;
use Comfino\Tests\TestState;

/**
 * A cancellation that ComfinoPay itself caused (status notification) must never be echoed back as a cancel request,
 * whatever the site language. A genuine shop cancellation is still sent, exactly once.
 */
class RedundantCancelGuardTest extends \PHPUnit_Framework_TestCase
{
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

        ConfigManager::getInstance()->setConfigurationValue('COMFINO_ENABLED', true);
        ConfigManager::getInstance()->setConfigurationValue('COMFINO_API_KEY', 'TEST_API_KEY_0123456789');

        // WooCommerce fires woocommerce_order_status_changed synchronously inside update_status().
        $GLOBALS['comfino_test_order_status_changed'] = static function (\WC_Order $order, string $oldStatus, string $newStatus): void {
            ShopStatusManager::orderStatusUpdateEventHandler($order, $oldStatus, $newStatus);
        };

        // No status notes on the order unless a test adds them.
        $GLOBALS['comfino_test_order_notes'] = [];
    }

    public function tearDown(): void
    {
        TestState::reset();

        parent::tearDown();
    }

    public function testWebhookCancellationNotEchoed(): void
    {
        // Polish site: the status note is written translated, which defeated the pre-4.4.0 English-only note match.
        $GLOBALS['comfino_test_translations'] = ['ComfinoPay status' => 'Status ComfinoPay'];

        foreach (['REJECTED', 'CANCELLED', 'CANCELLED_BY_SHOP'] as $status) {
            $this->applyWebhookStatus('123', $status);
        }

        // RESIGN is not in the default status map: it doesn't cancel the order, but it is recorded as terminal.
        $this->applyWebhookStatus('123', 'RESIGN');

        $this->assertSame(0, $this->apiClient->getCallCount('cancelOrder'));
    }

    public function testCustomStatusMapToCancelledNotEchoed(): void
    {
        ConfigManager::getInstance()->setConfigurationValue('COMFINO_STATUS_MAP', ['WAITING_FOR_PAYMENT' => 'cancelled']);

        $this->applyWebhookStatus('123', 'WAITING_FOR_PAYMENT');

        $this->assertSame(0, $this->apiClient->getCallCount('cancelOrder'));
    }

    public function testNestedSetStatusKeepsContext(): void
    {
        $context = StatusApplicationContext::forScope();

        $context->apply(function () use ($context): void {
            $context->apply(static function (): void {
            });

            $this->assertTrue(StatusApplicationContext::isActive(), 'An inner apply() must not clear the outer guard.');
            $this->assertSame(ShopStatusManager::CANCEL_SUPPRESSED_BY_CONTEXT, ShopStatusManager::getCancelSuppressionReason(new \WC_Order()));
        });

        $this->assertFalse(StatusApplicationContext::isActive());
    }

    public function testTerminalMetaSuppressesAdminCancel(): void
    {
        $order = new \WC_Order();
        $order->update_meta_data(ShopStatusManager::TERMINAL_STATUS_META_KEY, 'REJECTED');

        // Admin cancellation in a separate request: no webhook context.
        ShopStatusManager::orderStatusUpdateEventHandler($order, 'pending', 'cancelled');

        $this->assertSame(ShopStatusManager::CANCEL_SUPPRESSED_BY_META, ShopStatusManager::getCancelSuppressionReason($order));
        $this->assertSame(0, $this->apiClient->getCallCount('cancelOrder'));
    }

    public function testWebhookStoresTerminalStatusMeta(): void
    {
        $order = null;

        $GLOBALS['comfino_test_order_status_changed'] = static function (\WC_Order $changedOrder) use (&$order): void {
            $order = $changedOrder;
        };

        $this->applyWebhookStatus('123', 'REJECTED');

        $this->assertInstanceOf(\WC_Order::class, $order);
        $this->assertSame('REJECTED', $order->get_meta(ShopStatusManager::TERMINAL_STATUS_META_KEY));
    }

    public function testLegacyNoteVariantsSuppress(): void
    {
        $variants = [
            'Comfino status: REJECTED',
            'Status Comfino: CANCELLED',
            'ComfinoPay status: CANCELLED_BY_SHOP',
            'Status ComfinoPay: RESIGN',
        ];

        foreach ($variants as $noteContent) {
            // Note author is translated too, so it is not checked.
            $GLOBALS['comfino_test_order_notes'] = [(object) ['added_by' => 'WooCommerce', 'content' => $noteContent]];

            $order = new \WC_Order();

            ShopStatusManager::orderStatusUpdateEventHandler($order, 'pending', 'cancelled');

            $this->assertSame(ShopStatusManager::CANCEL_SUPPRESSED_BY_NOTE, ShopStatusManager::getCancelSuppressionReason($order), $noteContent);
        }

        $this->assertSame(0, $this->apiClient->getCallCount('cancelOrder'));
    }

    public function testNonTerminalNoteDoesNotSuppress(): void
    {
        $GLOBALS['comfino_test_order_notes'] = [
            (object) ['added_by' => 'system', 'content' => 'Comfino status: ACCEPTED'],
            (object) ['added_by' => 'system', 'content' => 'Comfino status: rejected'],
        ];

        $this->assertNull(ShopStatusManager::getCancelSuppressionReason(new \WC_Order()));
    }

    public function testGenuineShopCancellationSent(): void
    {
        $order = new \WC_Order();

        ShopStatusManager::orderStatusUpdateEventHandler($order, 'processing', 'cancelled');

        $this->assertSame(1, $this->apiClient->getCallCount('cancelOrder'));
        $this->assertSame(['123'], $this->apiClient->cancelledOrderIds);
        $this->assertSame(['Order cancellation sent to ComfinoPay.'], $order->notes);
    }

    public function testFailedShopCancellationGetsDistinctNote(): void
    {
        $this->apiClient->cancelOrderException = new \RuntimeException('Connection refused');

        $order = new \WC_Order();

        ShopStatusManager::orderStatusUpdateEventHandler($order, 'processing', 'cancelled');

        $this->assertSame(1, $this->apiClient->getCallCount('cancelOrder'));
        $this->assertSame(['Order cancellation could not be sent to ComfinoPay.'], $order->notes);
    }

    private function applyWebhookStatus(string $orderId, string $status): void
    {
        // The shared StatusManager::setOrderStatus() wraps every status notification in the application context.
        StatusApplicationContext::forScope()->apply(static function () use ($orderId, $status): void {
            (new StatusAdapter())->setStatus($orderId, $status);
        });
    }
}
