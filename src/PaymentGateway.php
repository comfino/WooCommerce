<?php

namespace Comfino;

use Comfino\Api\ApiClient;
use Comfino\Api\ApiService;
use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Common\Backend\Factory\OrderFactory;
use Comfino\Configuration\ConfigManager;
use Comfino\Configuration\SettingsManager;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Order\OrderManager;
use Comfino\Order\ShopStatusManager;
use Comfino\Shop\Order\Customer;
use Comfino\Shop\Order\Customer\Address;

class PaymentGateway extends \WC_Payment_Gateway
{
    public const VERSION = '4.0.0';

    public function __construct()
    {
        $this->id = 'comfino';
        $this->icon = $this->get_icon();
        $this->has_fields = true;
        $this->method_title = __('Comfino payments', 'comfino-payment-gateway');
        $this->method_description = __(
            'Comfino is an innovative payment method for customers of e-commerce stores! ' .
            'These are installment payments, deferred (buy now, pay later) and corporate ' .
            'payments available on one platform with the help of quick integration. Grow your business with Comfino!',
            'comfino-payment-gateway'
        );

        $this->supports = ['products'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');

        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        //add_action('woocommerce_api_comfino_gateway', [$this, 'webhook']);
        //add_action('woocommerce_order_status_cancelled', [ShopStatusManager::class, 'orderStatusCancelEventHandler']);
        add_action('woocommerce_order_status_changed', [$this, 'order_status_changed'], 10, 3);

        add_filter(
            'woocommerce_available_payment_gateways',
            static function (array $gateways): array {
                if (is_wc_endpoint_url('order-pay')) {
                    $order = wc_get_order(absint(get_query_var('order-pay')));

                    if ($order instanceof \WC_Order && $order->has_status('failed')) {
                        if (ConfigManager::getConfigurationValue('COMFINO_ABANDONED_PAYMENTS') === 'comfino') {
                            foreach ($gateways as $name => $gateway) {
                                if ($name !== 'comfino') {
                                    unset($gateways[$name]);
                                }
                            }
                        } else {
                            foreach ($gateways as $name => $gateway) {
                                if ($name !== 'comfino') {
                                    $gateway->chosen = false;
                                } else {
                                    $gateway->chosen = true;
                                }
                            }
                        }
                    }
                }

                return $gateways;
            },
            1
        );
    }

    public function get_icon(): string
    {
        if (ConfigManager::getConfigurationValue('COMFINO_SHOW_LOGO')) {
            $icon = '<img style="height: 18px; margin: 0 5px;" src="' . ApiClient::getPaywallLogoUrl() . '" alt="Comfino" />';
        } else {
            $icon = '';
        }

        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }

    public function payment_fields(): void
    {
        $cart = WC()->cart;
        $total = (int) ($cart->get_total('edit') * 100);

        if (is_wc_endpoint_url('order-pay')) {
            $order = wc_get_order(absint(get_query_var('order-pay')));
            $total = (int) ($order->get_total('edit') * 100);
        }

        echo Main::renderPaywallIframe($cart, $total);
        /*echo $this->prepare_paywall_iframe(
            (float) $total,
            $this->get_product_types_filter($cart, new Config_Manager()),
            Api_Client::$widget_key
        );*/
    }

    public function process_payment($order_id): array
    {
        $orderId = (string) $order_id;
        $shopCart = OrderManager::getShopCart(WC()->cart, (int) sanitize_text_field($_POST['comfino_loan_amount']));

        $wcOrder = wc_get_order($order_id);
        $phoneNumber = trim($wcOrder->get_billing_phone());

        if (empty($phoneNumber)) {
            // Try to find phone number in order metadata.
            $orderMetadata = $wcOrder->get_meta_data();

            foreach ($orderMetadata as $metaDataItem) {
                /** @var \WC_Meta_Data $metaDataItem */
                $metaData = $metaDataItem->get_data();

                if (stripos($metaData['key'], 'tel') !== false || stripos($metaData['key'], 'phone') !== false) {
                    $metaValue = str_replace(['-', ' ', '(', ')'], '', trim($metaData['value']));

                    if (preg_match('/^(?:\+?\d{1,2})?\d{9}$|^(?:\d{2,3})?\d{7}$/', $metaValue)) {
                        $phoneNumber = $metaValue;

                        break;
                    }
                }
            }
        }

        $firstName = $wcOrder->get_billing_first_name();
        $lastName = $wcOrder->get_billing_last_name();

        if ($lastName === '') {
            $name = explode(' ', $firstName);

            if (count($name) > 1) {
                [$firstName, $lastName] = $name;
            }
        }

        $billingAddressLines = $wcOrder->get_billing_address_1();

        if (!empty($wcOrder->get_billing_address_2())) {
            $billingAddressLines .= " {$wcOrder->get_billing_address_2()}";
        }

        $street = trim($billingAddressLines);
        $addressParts = explode(' ', $street);
        $buildingNumber = '';

        if (count($addressParts) > 1) {
            foreach ($addressParts as $idx => $addressPart) {
                if (preg_match('/^\d+[a-zA-Z]?$/', trim($addressPart))) {
                    $street = implode(' ', array_slice($addressParts, 0, $idx));
                    $buildingNumber = trim($addressPart);
                }
            }
        }

        /** @see https://woocommerce.com/document/eu-vat-number/ */
        $customerTaxId = function_exists('wc_eu_vat_get_vat_from_order') ? trim(wc_eu_vat_get_vat_from_order($wcOrder)) : '';

        $order = (new OrderFactory())->createOrder(
            $orderId,
            $shopCart->getTotalValue(),
            $shopCart->getDeliveryCost(),
            (int) sanitize_text_field($_POST['comfino_loan_term']),
            new LoanTypeEnum(sanitize_text_field($_POST['comfino_loan_type'])),
            $shopCart->getCartItems(),
            new Customer(
                $firstName,
                $lastName,
                $wcOrder->get_billing_email(),
                $phoneNumber,
                \WC_Geolocation::get_ip_address(),
                preg_match('/^[A-Z]{0,3}\d{7,}$/', $customerTaxId) ? $customerTaxId : null,
                $wcOrder->get_user() !== false,
                is_user_logged_in(),
                new Address(
                    $street,
                    $buildingNumber,
                    null,
                    $wcOrder->get_billing_postcode(),
                    $wcOrder->get_billing_city(),
                    $wcOrder->get_billing_country()
                )
            ),
            $this->get_return_url($wcOrder),
            ApiService::getEndpointUrl('transactionStatus'),
            SettingsManager::getAllowedProductTypes(ProductTypesListTypeEnum::LIST_TYPE_PAYWALL, $shopCart)
        );

        return [];
    }

    public function order_status_changed(int $order_id, string $status_old, string $status_new): void
    {
        ShopStatusManager::orderStatusUpdateEventHandler(wc_get_order($order_id), $status_old, $status_new);
    }
}
