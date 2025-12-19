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
}
