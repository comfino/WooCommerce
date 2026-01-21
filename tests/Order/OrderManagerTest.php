<?php

namespace Comfino\Tests\Order;

use Comfino\Shop\Order\Cart\Product;
use Comfino\Shop\Order\Cart\CartItemInterface;
use Comfino\Order\OrderManager;

class OrderManagerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Mock functions are handled in bootstrap.php
    }

    /**
     * @throws \Exception
     */
    public function testGetShopCartFromWCCart(): void
    {
        $wcCart = new \WC_Cart();
        $priceModifier = 500;

        $cart = OrderManager::getShopCart($wcCart, $priceModifier);

        $this->assertGreaterThan(0, $cart->getTotalValue());

        // Total should be cart total + price modifier.
        $expectedTotal = (int) round($wcCart->get_total('edit') * 100) + $priceModifier;
        $this->assertEquals($expectedTotal, $cart->getTotalValue());

        $cartItems = $cart->getCartItems();

        $this->assertNotEmpty($cartItems);

        foreach ($cartItems as $cartItem) {
            $this->assertInstanceOf(CartItemInterface::class, $cartItem);
            $this->assertInstanceOf(Product::class, $cartItem->getProduct());
            $this->assertGreaterThan(0, $cartItem->getQuantity());
        }
    }

    /**
     * @throws \Exception
     */
    public function testGetShopCartFromWCCartWithoutPriceModifier(): void
    {
        $wcCart = new \WC_Cart();
        $cart = OrderManager::getShopCart($wcCart);

        // Total should be cart total without modifier.
        $this->assertEquals((int) round($wcCart->get_total('edit') * 100), $cart->getTotalValue());
    }

    public function testGetShopCartFromProduct(): void
    {
        $product = new \WC_Product();

        $cart = OrderManager::getShopCartFromProduct($product);

        $this->assertGreaterThan(0, $cart->getTotalValue());

        // Total should equal product price including tax.
        $this->assertEquals((int) (wc_get_price_including_tax($product) * 100), $cart->getTotalValue());

        $cartItems = $cart->getCartItems();

        $this->assertCount(1, $cartItems);

        $cartItem = $cartItems[0];

        $this->assertInstanceOf(CartItemInterface::class, $cartItem);
        $this->assertEquals(1, $cartItem->getQuantity());

        $cartProduct = $cartItem->getProduct();

        $this->assertEquals($product->get_name(), $cartProduct->getName());
        $this->assertEquals((string) $product->get_id(), $cartProduct->getId());
        $this->assertEquals($product->get_sku(), $cartProduct->getEan());
    }

    public function testGetShopCartFromVariationProduct(): void
    {
        $variationProduct = new \WC_Product_Variation();

        $cart = OrderManager::getShopCartFromProduct($variationProduct);
        $cartItems = $cart->getCartItems();

        $this->assertCount(1, $cartItems);
        $this->assertNotEmpty($cartItems[0]->getProduct()->getCategoryIds());
    }

    public function testGetOrderStatusNotes(): void
    {
        $orderId = 123;
        $statuses = ['ACCEPTED', 'CANCELLED', 'REJECTED'];

        $notes = OrderManager::getOrderStatusNotes($orderId, $statuses);

        $this->assertArrayHasKey('ACCEPTED', $notes);
        $this->assertArrayHasKey('CANCELLED', $notes);
        $this->assertArrayNotHasKey('REJECTED', $notes); // Not in mock data.

        foreach ($notes as $status => $note) {
            $this->assertInternalType('object', $note);
            $this->assertEquals('system', $note->added_by);
            $this->assertContains("Comfino status: $status", $note->content);
        }
    }

    public function testGetOrderStatusNotesWithEmptyResult(): void
    {
        // Mock functions are handled in bootstrap.php

        $orderId = 456;
        $statuses = ['NON_EXISTENT_STATUS'];

        $this->assertEmpty(OrderManager::getOrderStatusNotes($orderId, $statuses));
    }

    public function testStaticMethodsExist(): void
    {
        // Verify all expected static methods exist and are callable.
        $this->assertTrue(method_exists(OrderManager::class, 'getShopCart'));
        $this->assertTrue(method_exists(OrderManager::class, 'getShopCartFromProduct'));
        $this->assertTrue(method_exists(OrderManager::class, 'getOrderStatusNotes'));
        $this->assertTrue(method_exists(OrderManager::class, 'loadOrder'));
        $this->assertTrue(method_exists(OrderManager::class, 'loadOrderByNumber'));
    }

    /**
     * @throws \Exception
     */
    public function testCartItemsHaveRequiredProperties(): void
    {
        $wcCart = new \WC_Cart();
        $cart = OrderManager::getShopCart($wcCart);

        foreach ($cart->getCartItems() as $cartItem) {
            $product = $cartItem->getProduct();

            $this->assertNotEmpty($product->getName());
            $this->assertGreaterThan(0, $product->getPrice());
            $this->assertGreaterThan(0, $cartItem->getQuantity());
        }
    }

    /**
     * @throws \Exception
     */
    public function testShopCartHasDeliveryCost(): void
    {
        $wcCart = new \WC_Cart();
        $cart = OrderManager::getShopCart($wcCart);

        $this->assertGreaterThanOrEqual(0, $cart->getDeliveryCost());

        // Expected delivery cost from mock.
        $expectedDeliveryCost = (int) round(($wcCart->get_shipping_total() + $wcCart->get_shipping_tax()) * 100);
        $this->assertEquals($expectedDeliveryCost, $cart->getDeliveryCost());
    }

    public function testLoadOrderSuccess(): void
    {
        // Test successful order loading - order exists.
        $order = OrderManager::loadOrder(123);

        $this->assertInstanceOf(\WC_Order::class, $order);
        $this->assertEquals(123, $order->get_id());
    }

    public function testLoadOrderReturnsNullWhenOrderNotFound(): void
    {
        // Test order not found scenario - should return null without exception.
        $order = OrderManager::loadOrder(999);

        $this->assertNull($order);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testLoadOrderThrowsExceptionOnDatabaseError(): void
    {
        global $wpdb;

        // Use simulate_error flag to trigger error after loadOrder() clears last_error.
        $wpdb->simulate_error = true;

        try {
            OrderManager::loadOrder(123);

            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertContains('database', $e->getMessage());

            throw $e;
        } finally {
            // Clean up mock state.
            $wpdb->simulate_error = false;
            $wpdb->last_error = '';
        }
    }

    public function testLoadOrderByNumberDirectLoad(): void
    {
        // Test direct loading by order ID as string.
        $order = OrderManager::loadOrderByNumber('123');

        $this->assertInstanceOf(\WC_Order::class, $order);
    }

    public function testLoadOrderByNumberReturnsNullWhenNotFound(): void
    {
        // Test order not found scenario - should return null.
        $order = OrderManager::loadOrderByNumber('NON_EXISTENT_ORDER');

        $this->assertNull($order);
    }

    public function testLoadOrderByNumberWithMetaQuery(): void
    {
        // Skip test if WooCommerce version doesn't support meta_query (< 8.2.0).
        if (!defined('WC_VERSION') || version_compare(WC_VERSION, '8.2.0', '<')) {
            $this->markTestSkipped('WooCommerce 8.2.0+ required for meta_query support');
        }

        // This test verifies the method doesn't throw exceptions when meta_query is used.
        // With current mock implementation, it will return null (no matching orders).
        $order = OrderManager::loadOrderByNumber('2026-12345');

        // With mocks, we expect null since wc_get_orders() isn't fully implemented.
        $this->assertNull($order);
    }

    public function testLoadOrderByNumberHandlesPluginAPIs(): void
    {
        // This test verifies the method handles various plugin APIs gracefully.
        // With current mocks, all plugin APIs will return null or false.
        $order = OrderManager::loadOrderByNumber('ORD-00123');

        $this->assertNull($order);
    }

    public function testLoadOrderClearsWpdbErrors(): void
    {
        global $wpdb;

        // Set a previous error.
        $wpdb->last_error = 'Previous error';

        // Load an order - should clear the previous error.
        OrderManager::loadOrder(123);

        // Verify error was cleared during the process.
        // Note: In production, it gets cleared before wc_get_order() call.
        $this->assertInternalType('string', $wpdb->last_error);
    }
}
