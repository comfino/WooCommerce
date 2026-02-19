<?php /** @noinspection PhpExpressionResultUnusedInspection */

namespace Comfino;

use Comfino\Api\ApiClient;
use Comfino\Api\ApiService;
use Comfino\Api\Dto\Payment\FinancialProduct;
use Comfino\Api\Dto\Payment\LoanQueryCriteria;
use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Common\Backend\ConfigurationManager;
use Comfino\Common\Backend\Factory\OrderFactory;
use Comfino\Common\Shop\Cart;
use Comfino\Configuration\ConfigManager;
use Comfino\Configuration\SettingsManager;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Order\OrderManager;
use Comfino\Order\ShopStatusManager;
use Comfino\Shop\Order\Customer;
use Comfino\Shop\Order\Order;
use Comfino\Shop\Order\OrderInterface;
use Comfino\View\FrontendManager;
use Comfino\View\SettingsForm;
use Comfino\View\TemplateManager;
use ComfinoExternal\Psr\Http\Client\ClientExceptionInterface;

if (!defined('ABSPATH')) {
    exit;
}

class PaymentGateway extends \WC_Payment_Gateway
{
    public const GATEWAY_ID = 'comfino';
    public const VERSION = '4.2.8';
    public const BUILD_TS = 1771246114;
    public const WIDGET_INIT_SCRIPT_HASH = '0603f4e0904fd65e2aef1aded0c57c40';
    public const WIDGET_INIT_SCRIPT_LAST_HASH = '55e4306bb493ff6f99b2f8f617e18038';

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

        if (is_admin() && strpos(Main::getCurrentUrl(), $this->id) === false && strpos(Main::getCurrentUrl(), 'wc-orders') === false) {
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
                        if (ConfigManager::getConfigurationValue('COMFINO_ABANDONED_PAYMENTS') === PaymentGateway::GATEWAY_ID) {
                            foreach ($gateways as $name => $gateway) {
                                if ($name !== PaymentGateway::GATEWAY_ID) {
                                    unset($gateways[$name]);
                                }
                            }
                        } else {
                            foreach ($gateways as $name => $gateway) {
                                if ($name !== PaymentGateway::GATEWAY_ID) {
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
        return parent::is_available() && Main::paymentIsAvailable(WC()->cart);
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
        $cart = WC()->cart;

        DebugLogger::logEvent(
            '[PAYMENT GATEWAY]',
            'process_payment',
            ['cart_id' => $cart->get_cart_hash(), '$order_id' => $order_id, '$_POST' => $_POST]
        );

        $wcOrder = wc_get_order($order_id);

        // Get order ID or reference based on configuration.
        if ($useOrderReference = ConfigManager::isOrderReferenceEnabled()) {
            $orderId = !empty($wcOrder->get_order_number()) ? $wcOrder->get_order_number() : (string) $order_id;
        } else {
            $orderId = (string) $order_id;
        }

        DebugLogger::logEvent(
            '[PAYMENT]',
            'Order identifier for Comfino API',
            [
                'use_reference' => $useOrderReference,
                'order_id' => $orderId,
                'reference' => $wcOrder->get_order_number() ?? 'not_set',
            ]
        );

        $initLoanAmount = (int) filter_var(sanitize_text_field(wp_unslash($_POST['comfino_loan_amount'] ?? '0')), FILTER_VALIDATE_INT);
        $priceModifier = (int) filter_var(sanitize_text_field(wp_unslash($_POST['comfino_price_modifier'] ?? '0')), FILTER_VALIDATE_INT);
        $loanType = sanitize_text_field(wp_unslash($_POST['comfino_loan_type'] ?? 'undefined'));
        $loanTerm = (int) filter_var(sanitize_text_field(wp_unslash($_POST['comfino_loan_term'] ?? '0')), FILTER_VALIDATE_INT);

        try {
            $shopCart = OrderManager::getShopCart($cart, $priceModifier);
        } catch (\Exception $e) {
            wc_add_notice(FrontendManager::processError('Shop cart creation error', $e)['title'], 'error');

            return ['result' => 'failure', 'redirect' => ''];
        }

        if (empty($loanType) || empty($loanTerm)) {
            // Preselected financial offer data incomplete - load financial offer again and set default product as first user choice before redirection.
            if (empty($financialProducts = $this->getFinancialProducts($shopCart->getTotalValue()))) {
                // Emergency offer loading failed - return error to the user and prevent redirection to avoid transaction failure.
                wc_add_notice(__('Preselected financial offer data incomplete. Please try again.', 'comfino-payment-gateway'));

                return ['result' => 'failure', 'redirect' => ''];
            }

            // Use first offer with default term as an emergency preselection before redirection to the external Comfino page.
            $loanType = (string) $financialProducts[0]->type;
            $loanTerm = $financialProducts[0]->loanTerm;
        }

        $shopCustomer = OrderManager::getShopCustomerFromOrder($wcOrder);
        $returnUrl = $this->get_return_url($wcOrder);

        $order = $this->createOrder($orderId, $loanType, $loanTerm, $returnUrl, $shopCart, $shopCustomer);

        // Perform comprehensive validation before sending credit application.
        $validationErrors = $this->validatePaymentData($order, $cart);

        if (!empty($validationErrors)) {
            DebugLogger::logEvent(
                '[PAYMENT]',
                'Validation failed',
                ['errors' => $validationErrors, 'cart_id' => $cart->get_cart_hash(), '$order_id' => $order_id]
            );

            if ($this->isBlocksCheckout()) {
                wc_add_notice(implode("\n", $validationErrors), 'error');
            } else {
                foreach ($validationErrors as $error) {
                    wc_add_notice($error, 'error');
                }
            }

            return ['result' => 'failure', 'redirect' => ''];
        }

        DebugLogger::logEvent(
            '[PAYMENT]',
            'Validation passed - proceeding with order creation',
            [
                '$initLoanAmount' => $initLoanAmount,
                '$priceModifier' => $priceModifier,
                '$cartTotalValue' => $shopCart->getTotalValue(),
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

            // Mark order stock as reduced to enable stock restoration on cancellation.
            $wcOrder->get_data_store()->set_stock_reduced($wcOrder->get_id(), true);

            $cart->empty_cart();

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
            'cache_root_path' => Main::getCacheRootPath(),
            'cache_path' => Main::getCachePath(),
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
            $viewVariables['new_widget_status'] = ConfigManager::getConfigurationValue('COMFINO_NEW_WIDGET_ACTIVE') ? 'Active' : 'Inactive';
            $viewVariables['is_dev_env'] = ConfigManager::useDevEnvVars();
            $viewVariables['build_ts'] = \DateTime::createFromFormat('U', self::BUILD_TS)->format('Y-m-d H:i:s');

            // Get GitHub version information.
            $githubVersionData = get_transient('comfino_github_version_check');
            $viewVariables['github_version'] = !empty($githubVersionData['github_version']) ? $githubVersionData['github_version'] : null;
            $viewVariables['github_version_checked_at'] = !empty($githubVersionData['checked_at']) ? $githubVersionData['checked_at'] : null;
            $viewVariables['auto_updates_enabled'] = in_array(plugin_basename(Main::getPluginFile()), (array) get_site_option(implode('_', ['auto', 'update', 'plugins']), []), true);
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
            if (($fieldType = $this->get_field_type($field)) === 'hidden' || $fieldType === 'title') {
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

            if ($activeTab === 'sale_settings') {
                $productCategories = array_keys(ConfigManager::getAllProductCategories());
                $productCategoryFilters = [];

                foreach ($configurationOptions['product_categories'] as $productType => $categoryIds) {
                    $productCategoryFilters[$productType] = array_values(array_diff(
                        $productCategories,
                        explode(',', $configurationOptions['product_categories'][$productType])
                    ));
                }

                $configurationOptionsToSave[$optionsMap['product_category_filters']] = $productCategoryFilters;
            } elseif (array_key_exists($fieldKey, $configurationOptions)) {
                try {
                    if ($configurationOptions[$fieldKey] === 'yes' || $configurationOptions[$fieldKey] === 'no') {
                        $configurationOptionsToSave[$optionsMap[$key]] = ($configurationOptions[$fieldKey] === 'yes');
                    } elseif ($key === 'widget_offer_types') {
                        $configurationOptions[$fieldKey] = implode(',', $configurationOptions[$fieldKey]);
                        $configurationOptionsToSave[$optionsMap[$key]] = explode(',', $this->get_field_value($key, $field, $configurationOptions));
                    } else {
                        $configurationOptionsToSave[$optionsMap[$key]] = $this->get_field_value($key, $field, $configurationOptions);
                    }
                } catch (\Exception $e) {
                    $errorMessages[] = $e->getMessage();
                }
            } elseif ($key === 'widget_offer_types') {
                $configurationOptionsToSave[$optionsMap[$key]] = [];
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
            FrontendManager::includeLocalScripts(['tree.min.js'], [], false, false);
        }
    }

    public function generate_hidden_html(string $key, array $data): string
    {
        if (is_array($optionValue = ConfigManager::getConfigurationValueByInternalName($key))) {
            $optionValue = implode(',', $optionValue);
        }

        return FrontendManager::renderHiddenInput(
            $this->get_field_key($key),
            $optionValue,
            $data,
            $this
        );
    }

    public function generate_checkboxset_html(string $key, $data): string
    {
        if (!is_array($optionValue = ConfigManager::getConfigurationValueByInternalName($key))) {
            $optionValue = explode(',', $optionValue);
        }

        return FrontendManager::renderCheckboxSet(
            $this->get_field_key($key),
            $optionValue,
            $data,
            $this
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

    /**
     * Detects if the current checkout is using WooCommerce Blocks.
     */
    private function isBlocksCheckout(): bool
    {
        // Primary check: REST API request.
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        // Secondary check: WooCommerce Store API.
        if (function_exists('wc_is_rest_api_request') && wc_is_rest_api_request()) {
            return true;
        }

        // Tertiary check: Request URI contains Store API path.
        if (isset($_SERVER['REQUEST_URI']) && strpos(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])), '/wp-json/wc/store/') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Validates payment data from Order object before processing.
     *
     * @return string[] Array of error messages, empty if validation passes.
     */
    private function validatePaymentData(OrderInterface $order, \WC_Cart $cart): array
    {
        $errors = [];

        // 1. Validate customer e-mail.
        $customerEmail = $order->getCustomer()->getEmail();

        if (empty($customerEmail) || !is_email($customerEmail)) {
            $errors[] = __('Invalid customer e-mail address. Please check your account contact data.', 'comfino-payment-gateway');
        }

        // 2. Validate phone number.
        $phoneNumber = $order->getCustomer()->getPhoneNumber();

        if (empty($phoneNumber)) {
            $errors[] = __(
                'Phone number is required. Please add a phone number to your billing or delivery address.',
                'comfino-payment-gateway'
            );
        }

        // 3. Validate customer names.
        if (empty(trim($order->getCustomer()->getFirstName()))) {
            $errors[] = __('First name is required.', 'comfino-payment-gateway');
        }

        if (empty(trim($order->getCustomer()->getLastName()))) {
            $errors[] = __('Last name is required.', 'comfino-payment-gateway');
        }

        // 4. Validate customer address.
        $address = $order->getCustomer()->getAddress();

        if ($address === null) {
            $errors[] = __('Delivery address is required.', 'comfino-payment-gateway');
        } else {
            if (empty(trim($address->getCity()))) {
                $errors[] = __('City/Town is required.', 'comfino-payment-gateway');
            }

            if (empty(trim($address->getPostalCode()))) {
                $errors[] = __('Postal code is required.', 'comfino-payment-gateway');
            }
        }

        // 5. Validate cart data.
        $cartItems = $order->getCart()->getItems();

        if (empty($cartItems)) {
            $errors[] = __('Cart is empty. Please add products to your cart.', 'comfino-payment-gateway');
        }

        // 6. Validate order amount.
        if ($order->getCart()->getTotalAmount() <= 0) {
            $errors[] = __('Cart total amount must be greater than zero.', 'comfino-payment-gateway');
        }

        // 7. Validate payment availability.
        if (!Main::paymentIsAvailable($cart)) {
            $errors[] = __(
                'Comfino payment is not available for this cart. Please check cart amount and product types.',
                'comfino-payment-gateway'
            );
        }

        if (!empty($errors)) {
            // Do not call validation at Comfino API side if any errors detected locally.
            return $errors;
        }

        // Call Comfino API validation as a second step of order validation if no errors detected locally.
        $validationResult = ApiClient::getInstance()->validateOrder($order);

        if (!$validationResult->success) {
            $errors = array_values($validationResult->errors);
        }

        return $errors;
    }

    /**
     * @return FinancialProduct[]
     */
    private function getFinancialProducts(int $loanAmount): array
    {
        try {
            return ApiClient::getInstance()->getFinancialProducts(new LoanQueryCriteria($loanAmount))->financialProducts;
        } catch (ClientExceptionInterface $e) {
            FrontendManager::processError('Emergency financial offer retrieving error', $e);

            return [];
        }
    }

    private function createOrder(
        string $orderId,
        string $loanType,
        int $loanTerm,
        string $returnUrl,
        Cart $shopCart,
        Customer $shopCustomer
    ): Order
    {
        return (new OrderFactory())->createOrder(
            $orderId,
            $shopCart->getTotalValue(),
            $shopCart->getDeliveryCost(),
            $loanTerm,
            new LoanTypeEnum($loanType, false),
            $shopCart->getCartItems(),
            $shopCustomer,
            $returnUrl,
            ApiService::getEndpointUrl('transactionStatus'),
            SettingsManager::getAllowedProductTypes(ProductTypesListTypeEnum::LIST_TYPE_PAYWALL, $shopCart),
            $shopCart->getDeliveryNetCost(),
            $shopCart->getDeliveryTaxRate(),
            $shopCart->getDeliveryTaxValue()
        );
    }
}
