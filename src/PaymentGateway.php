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
use Comfino\View\SettingsForm;
use Comfino\View\TemplateManager;

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

        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
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

    /* Shop cart checkout frontend logic. */

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
                'Order creation error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)',
                $e
            );

            wc_add_notice($e->getMessage(), 'error');

            $result = ['result' => 'failure', 'redirect' => ''];
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

        $activeTab = $this->get_subsection();

        $viewVariables = [
            'wp' => $wp,
            'title' => $this->method_title,
            'description' => $this->method_description,
            'active_tab' => $activeTab,
            'logo_url' => ApiClient::getLogoUrl(),
            'support_email_address' => SettingsForm::COMFINO_SUPPORT_EMAIL,
            'support_email_subject' => sprintf(
                __('WordPress %s WooCommerce %s Comfino %s - question', 'comfino-payment-gateway'),
                $wp_version,
                WC_VERSION,
                self::VERSION
            ),
            'support_email_body' => sprintf(
                'WordPress %s WooCommerce %s Comfino %s, PHP %s',
                $wp_version,
                WC_VERSION,
                self::VERSION,
                PHP_VERSION
            ),
            'contact_msg1' => __('Do you want to ask about something? Write to us at', 'comfino-payment-gateway'),
            'contact_msg2' => sprintf(
                __('or contact us by phone. We are waiting on the number: %s. We will answer all your questions!', 'comfino-payment-gateway'),
                SettingsForm::COMFINO_SUPPORT_PHONE
            ),
            'plugin_version' => self::VERSION,
        ];

        if ($activeTab === 'plugin_diagnostics') {
            $viewVariables['shop_info'] = sprintf(
                'WooCommerce Comfino %s, WordPress %s, WooCommerce %s, PHP %s, web server %s, database %s',
                ...array_values(ConfigManager::getEnvironmentInfo([
                    'plugin_version',
                    'wordpress_version',
                    'shop_version',
                    'php_version',
                    'server_software',
                    'database_version',
                ]))
            );
            $viewVariables['errors_log'] = ErrorLogger::getErrorLog(SettingsForm::ERROR_LOG_NUM_LINES);
            $viewVariables['debug_log'] = Main::getDebugLog(SettingsForm::DEBUG_LOG_NUM_LINES);
            $viewVariables['api_host'] = ApiClient::getInstance()->getApiHost();
            $viewVariables['shop_domain'] = Main::getShopDomain();
            $viewVariables['widget_key'] = ConfigManager::getWidgetKey();
            $viewVariables['is_dev_env'] = ApiClient::isDevEnv() ? 'Yes' : 'No';
        } else {
            $viewVariables['settings_html'] = $this->generate_settings_html(SettingsForm::getFormFields($activeTab), false);
        }

        echo TemplateManager::renderView('configuration', 'admin', $viewVariables);
    }

    public function process_admin_options(): bool
    {
        $activeTab = $this->get_subsection();

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
        if ($this->enabled === 'no') {
            return;
        }

        if ($hook === 'woocommerce_page_wc-settings') {
            wp_enqueue_script('prod_cat_tree', plugins_url('views/js/tree.min.js',  Main::getPluginFile()), [], null);
        }
    }

    public function generate_hidden_html(string $key, array $data): string
    {
        $field_key = $this->get_field_key($key);
        $defaults = [
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => [],
        ];

        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?>
        <input class="input-text regular-input <?php echo esc_attr($data['class']); ?>" type="<?php echo esc_attr($data['type']); ?>" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr($this->get_option($key)); ?>" placeholder="<?php echo esc_attr($data['placeholder']); ?>" <?php disabled($data['disabled'], true); ?> <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok. ?> />
        <?php

        return ob_get_clean();
    }

    public function generate_product_category_tree_html(string $key, array $data): string
    {
        $defaults = [
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => [],
            'id' => '',
            'product_type' => '',
            'selected_categories' => [],
        ];

        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?>
        <tr valign="top">
            <td class="forminp" colspan="2">
                <h3><?php echo esc_html($data['title']); ?></h3>
                <?php echo SettingsForm::renderCategoryTree($data['id'], $data['product_type'], $data['selected_categories']); ?>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    private function get_subsection(): string
    {
        $active_tab = $_GET['subsection'] ?? 'payment_settings';

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
}
