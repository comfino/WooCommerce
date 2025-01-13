<?php

namespace Comfino;

use Comfino\Api\ApiClient;
use Comfino\Api\ApiService;
use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Common\Backend\ConfigurationManager;
use Comfino\Common\Backend\Factory\OrderFactory;
use Comfino\Configuration\ConfigManager;
use Comfino\Configuration\SettingsManager;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Order\OrderManager;
use Comfino\Order\ShopStatusManager;
use Comfino\Shop\Order\Customer;
use Comfino\Shop\Order\Customer\Address;
use Comfino\View\FrontendManager;
use Comfino\View\SettingsForm;
use Comfino\View\TemplateManager;

class PaymentGateway extends \WC_Payment_Gateway
{
    public const GATEWAY_ID = 'comfino';
    public const VERSION = '4.2.0';
    public const BUILD_TS = 1736779371;
    public const WIDGET_INIT_SCRIPT_HASH = 'b1a0cae1a47d1c5b9264df3573c09c48';
    public const WIDGET_INIT_SCRIPT_LAST_HASH = '4f8e7fe2091417c2b345fb51f1587316';

    public function __construct()
    {
        $this->id = self::GATEWAY_ID;
        $this->icon = $this->get_icon();
        $this->has_fields = true;
        $this->method_title = __('Comfino payments', 'comfino-payment-gateway');
        $this->method_description = __(
            'Comfino is an innovative payment method for customers of e-commerce stores! These are installment payments, deferred (buy now, pay later) and corporate payments available on one platform with the help of quick integration. Grow your business with Comfino!',
            'comfino-payment-gateway'
        );
        $this->description = __(
            'The wide range of Comfino installment payments means fast and safe shopping without burdening your budget here and now. Unexpected expenses, larger purchases, or maybe you just prefer to pay later? With Comfino you have a choice! 0% installments, Convenient Installments and deferred payments "Buy now, pay later". All so that you can enjoy shopping without worrying about your finances.',
            'comfino-payment-gateway'
        );
        $this->supports = ['products'];
        $this->title = $this->get_option('title');

        if (is_admin() && strpos(Main::getCurrentUrl(), 'comfino') === false && strpos(Main::getCurrentUrl(), 'wc-orders') === false) {
            return;
        }

        // Initialize Comfino plugin.
        Main::init();

        $this->init_form_fields();
        $this->init_settings();

        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_order_status_changed', [$this, 'order_status_changed'], 10, 3);

        add_filter(
            'woocommerce_available_payment_gateways',
            static function (array $gateways): array {
                if (is_wc_endpoint_url('order-pay') && ConfigManager::isAbandonedCartEnabled()) {
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

    public function is_available(): bool
    {
        return parent::is_available() && Main::paymentIsAvailable(WC()->cart, WC()->cart !== null ? (int) ($this->get_order_total() * 100) : 0);
    }

    /* Shop cart checkout front logic. */

    public function get_icon(): string
    {
        if (ConfigManager::getConfigurationValue('COMFINO_SHOW_LOGO')) {
            $icon = FrontendManager::renderPaywallLogo();
        } else {
            $icon = '';
        }

        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }

    public function payment_fields(): void
    {
        $this->generatePaywallIframe(false);
    }

    public function process_payment($order_id): array
    {
        DebugLogger::logEvent('[PAYMENT GATEWAY]', 'process_payment', ['$order_id' => $order_id, '$_POST' => $_POST]);

        $orderId = (string) $order_id;
        $shopCart = OrderManager::getShopCart(WC()->cart, (int) sanitize_text_field(wp_unslash($_POST['comfino_loan_amount'] ?? '0')));

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

        if (empty($phoneNumber)) {
            $phoneNumber = trim($wcOrder->get_shipping_phone());
        }

        if (!empty(trim($wcOrder->get_billing_first_name()))) {
            // Use billing address to get customer names.
            [$firstName, $lastName] = $this->prepareCustomerNames($wcOrder->get_billing_first_name(), $wcOrder->get_billing_last_name());
        } else {
            // Use delivery address to get customer names.
            [$firstName, $lastName] = $this->prepareCustomerNames($wcOrder->get_shipping_first_name(), $wcOrder->get_shipping_last_name());
        }

        $billingAddressLines = $wcOrder->get_billing_address_1();

        if (!empty($wcOrder->get_billing_address_2())) {
            $billingAddressLines .= " {$wcOrder->get_billing_address_2()}";
        }

        if (empty($billingAddressLines)) {
            $deliveryAddressLines = $wcOrder->get_shipping_address_1();

            if (!empty($wcOrder->get_shipping_address_2())) {
                $deliveryAddressLines .= " {$wcOrder->get_shipping_address_2()}";
            }

            $street = trim($deliveryAddressLines);
        } else {
            $street = trim($billingAddressLines);
        }

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
            (int) sanitize_text_field(wp_unslash($_POST['comfino_loan_term'] ?? '0')),
            new LoanTypeEnum(sanitize_text_field(wp_unslash($_POST['comfino_loan_type'] ?? 'undefined'))),
            $shopCart->getCartItems(),
            new Customer(
                $firstName,
                $lastName,
                $wcOrder->get_billing_email(),
                $phoneNumber,
                \WC_Geolocation::get_ip_address(),
                preg_match('/^[A-Z]{0,3}\d{7,}$/', str_replace('-', '', $customerTaxId)) ? $customerTaxId : null,
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
            SettingsManager::getAllowedProductTypes(ProductTypesListTypeEnum::LIST_TYPE_PAYWALL, $shopCart),
            $shopCart->getDeliveryNetCost(),
            $shopCart->getDeliveryTaxRate(),
            $shopCart->getDeliveryTaxValue()
        );

        DebugLogger::logEvent(
            '[PAYMENT]',
            'process_payment',
            [
                '$loanAmount' => $order->getCart()->getTotalAmount(),
                '$loanType' => (string) $order->getLoanParameters()->getType(),
                '$loanTerm' => $order->getLoanParameters()->getTerm(),
                '$shopCart' => $shopCart->getAsArray(),
            ]
        );

        try {
            $response = ApiClient::getInstance()->createOrder($order);

            if ($wcOrder->get_status() === 'failed') {
                $wcOrder->update_status('pending');
            }

            $wcOrder->add_order_note(__("Comfino create order", 'comfino-payment-gateway'));

            wc_reduce_stock_levels($wcOrder);

            WC()->cart->empty_cart();

            $result = ['result' => 'success', 'redirect' => $response->applicationUrl];
        } catch (\Throwable $e) {
            ApiClient::processApiError(
                'Order creation error on page "' . Main::getCurrentUrl() . '" (Comfino API)',
                $e
            );

            wc_add_notice($e->getMessage(), 'error');

            $result = ['result' => 'failure', 'redirect' => ''];
        } finally {
            if (($apiRequest = ApiClient::getInstance()->getRequest()) !== null) {
                DebugLogger::logEvent(
                    '[CREATE_ORDER_API_REQUEST]',
                    'createOrder',
                    ['$request' => $apiRequest->getRequestBody()]
                );
            }
        }

        return $result;
    }

    public function order_status_changed(int $order_id, string $status_old, string $status_new): void
    {
        ShopStatusManager::orderStatusUpdateEventHandler(wc_get_order($order_id), $status_old, $status_new);
    }

    /* Shop admin backend logic. */

    public function init_form_fields(): void
    {
        $this->form_fields = SettingsForm::getFormFields();
    }

    public function admin_options(): void
    {
        global $wp, $wp_version;

        $activeTab = $this->getSubsection();

        $viewVariables = [
            'wp' => $wp,
            'title' => $this->method_title,
            'description' => $this->method_description,
            'active_tab' => $activeTab,
            'support_email_address' => SettingsForm::COMFINO_SUPPORT_EMAIL,
            'support_email_subject' => sprintf(
            /* translators: 1: WordPress version 2: WooCommerce version 3: Comfino plugin version */
                __('WordPress %1$s WooCommerce %2$s Comfino %3$s - question', 'comfino-payment-gateway'),
                $wp_version,
                WC_VERSION,
                self::VERSION
            ),
            'support_email_body' => sprintf(
                'WordPress %1$s WooCommerce %2$s Comfino %3$s, PHP %s',
                $wp_version,
                WC_VERSION,
                self::VERSION,
                PHP_VERSION
            ),
            'contact_msg1' => __('Do you want to ask about something? Write to us at', 'comfino-payment-gateway'),
            'contact_msg2' => sprintf(
            /* translators: s%: Comfino support telephone */
                __('or contact us by phone. We are waiting on the number: %s. We will answer all your questions!', 'comfino-payment-gateway'),
                SettingsForm::COMFINO_SUPPORT_PHONE
            ),
            'plugin_version' => self::VERSION,
            'comfino_logo_img' => FrontendManager::renderAdminLogo(),
            'comfino_logo_allowed_html' => FrontendManager::getImageAllowedHtml(),
        ];

        if ($activeTab === 'plugin_diagnostics') {
            $viewVariables['shop_info'] = sprintf(
                'WooCommerce Comfino %1$s, WordPress %2$s, WooCommerce %3$s, PHP %4$s, web server %5$s, database %6$s',
                ...array_values(ConfigManager::getEnvironmentInfo([
                    'plugin_version',
                    'wordpress_version',
                    'shop_version',
                    'php_version',
                    'server_software',
                    'database_version',
                ]))
            );
            $viewVariables['errors_log'] = ErrorLogger::getLoggerInstance()->getErrorLog(SettingsForm::ERROR_LOG_NUM_LINES);
            $viewVariables['debug_log'] = DebugLogger::getLoggerInstance()->getDebugLog(SettingsForm::DEBUG_LOG_NUM_LINES);
            $viewVariables['api_host'] = ApiClient::getInstance()->getApiHost();
            $viewVariables['shop_domain'] = Main::getShopDomain();
            $viewVariables['widget_key'] = ConfigManager::getWidgetKey();
            $viewVariables['is_dev_env'] = ConfigManager::isDevEnv() ? 'Yes' : 'No';
            $viewVariables['build_ts'] = \DateTime::createFromFormat('U', self::BUILD_TS)->format('Y-m-d H:i:s');
        } else {
            $viewVariables['settings_html'] = $this->generate_settings_html(SettingsForm::getFormFields($activeTab), false);
        }

        $viewVariables['settings_allowed_html'] = FrontendManager::getAdminPanelAllowedHtml();

        TemplateManager::renderView('configuration', 'admin', $viewVariables);
    }

    public function process_admin_options(): bool
    {
        $activeTab = $this->getSubsection();

        $configurationOptions = $this->get_post_data();
        $configurationOptionsToSave = [];
        $optionsMap = array_flip(ConfigManager::CONFIG_OPTIONS_MAP);
        $errorMessages = [];

        foreach (SettingsForm::getFormFields($activeTab) as $key => $field) {
            if (($fieldType = $this->get_field_type($field)) === 'hidden') {
                continue;
            }

            $fieldKey = $this->get_field_key($key);

            if (isset($optionsMap[$key]) && (ConfigManager::getConfigurationValueType($optionsMap[$key]) & ConfigurationManager::OPT_VALUE_TYPE_BOOL)) {
                if (isset($configurationOptions[$fieldKey])) {
                    $configurationOptions[$fieldKey] = 'yes';
                } else {
                    $configurationOptions[$fieldKey] = 'no';
                }
            }

            if (array_key_exists($fieldKey, $configurationOptions) && $fieldType !== 'title') {
                try {
                    if ($configurationOptions[$fieldKey] === 'yes' || $configurationOptions[$fieldKey] === 'no') {
                        $configurationOptionsToSave[$optionsMap[$key]] = ($configurationOptions[$fieldKey] === 'yes');
                    } else {
                        $configurationOptionsToSave[$optionsMap[$key]] = $this->get_field_value($key, $field, $configurationOptions);
                    }
                } catch (\Exception $e) {
                    $errorMessages[] = $e->getMessage();
                }
            } elseif ($activeTab === 'sale_settings') {
                $productCategories = array_keys(ConfigManager::getAllProductCategories());
                $productCategoryFilters = [];

                foreach ($configurationOptions['product_categories'] as $productType => $categoryIds) {
                    $productCategoryFilters[$productType] = array_values(array_diff(
                        $productCategories,
                        explode(',', $configurationOptions['product_categories'][$productType])
                    ));
                }

                $configurationOptionsToSave[$optionsMap['product_category_filters']] = $productCategoryFilters;
            }
        }

        $result = SettingsForm::processForm($activeTab, $configurationOptionsToSave, $configurationOptions);
        $errorMessages = array_merge($errorMessages, $result['errorMessages']);

        if (count($errorMessages) > 0) {
            foreach ($errorMessages as $errorMessage) {
                $this->add_error($errorMessage);
            }

            $this->display_errors();

            if (!$result['success']) {
                return false;
            }
        }

        return true;
    }

    public function admin_scripts($hook): void
    {
        if ($hook === 'woocommerce_page_wc-settings') {
            FrontendManager::includeLocalScripts(['tree.min.js'], [], false);
        }
    }

    public function generate_hidden_html(string $key, array $data): string
    {
        return FrontendManager::renderHiddenInput(
            $this->get_field_key($key),
            $this->get_option($key),
            $this->get_custom_attribute_html($data),
            $data
        );
    }

    public function generate_product_category_tree_html(string $key, array $data): string
    {
        return FrontendManager::renderProductCategoryTree($data);
    }

    public function generatePaywallIframe(bool $isPaymentBlock): string
    {
        return WC()->cart !== null ? Main::renderPaywallIframe(WC()->cart, $this->get_order_total(), $isPaymentBlock) : '';
    }

    public function getTotal(): float
    {
        return absint(get_query_var('order-pay')) > 0 || WC()->cart !== null ? $this->get_order_total() : 0;
    }

    private function getSubsection(): string
    {
        check_admin_referer('comfino_settings', 'comfino_nonce');

        $active_tab = sanitize_key(wp_unslash($_GET['subsection'] ?? 'payment_settings'));

        if (!in_array(
            $active_tab,
            [
                'payment_settings',
                'sale_settings',
                'widget_settings',
                'abandoned_cart_settings',
                'developer_settings',
                'plugin_diagnostics',
            ],
            true
        )) {
            $active_tab = 'payment_settings';
        }

        return $active_tab;
    }

    private function prepareCustomerNames(string $firstName, string $lastName): array
    {
        $firstName = trim($firstName);
        $lastName = trim($lastName);

        if (empty($lastName)) {
            $nameParts = explode(' ', $firstName);

            if (count($nameParts) > 1) {
                [$firstName, $lastName] = $nameParts;
            }
        }

        return [$firstName, $lastName];
    }
}
