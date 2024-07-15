<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Comfino\View;

use Comfino\Api\ApiClient;
use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Configuration\ConfigManager;
use Comfino\Configuration\SettingsManager;
use Comfino\ErrorLogger;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class FormManager
{
    private const ERROR_LOG_NUM_LINES = 100;

    public static function getSettingsForm(\PaymentModule $module, array $params): string
    {
        $configTab = $params['config_tab'] ?? '';
        $formName = $params['form_name'] ?? 'submit_configuration';

        $helper = self::getHelperForm($module, $formName);
        $helper->fields_value['active_tab'] = $configTab;

        foreach (ConfigManager::CONFIG_OPTIONS as $options) {
            foreach ($options as $optionName => $optionType) {
                $helper->fields_value[$optionName] = ConfigManager::getConfigurationValue($optionName);
            }
        }

        $helper->fields_value['COMFINO_WIDGET_ERRORS_LOG'] = ErrorLogger::getErrorLog(self::ERROR_LOG_NUM_LINES);

        $messages = [];

        switch ($configTab) {
            case 'payment_settings':
                if (ConfigManager::isSandboxMode()) {
                    $messages['warning'] = $module->l('Developer mode is active. You are using test environment.');
                }

                break;

            case 'sale_settings':
            case 'widget_settings':
                if (!isset($params['offer_types'][$configTab])) {
                    $params['offer_types'][$configTab] = SettingsManager::getProductTypesSelectList(
                        $configTab === 'sale_settings'
                            ? ProductTypesListTypeEnum::LIST_TYPE_PAYWALL
                            : ProductTypesListTypeEnum::LIST_TYPE_WIDGET
                    );
                }
                if (!isset($params['widget_types'])) {
                    $params['widget_types'] = SettingsManager::getWidgetTypesSelectList();
                }

                break;

            case 'plugin_diagnostics':
                $infoMessages = [];
                $successMessages = [];
                $warningMessages = [];
                $errorMessages = [];

                $infoMessages[] = sprintf(
                    'PrestaShop Comfino %s, PrestaShop %s, Symfony %s, PHP %s, web server %s, database %s',
                    ...array_values(ConfigManager::getEnvironmentInfo([
                        'plugin_version',
                        'shop_version',
                        'symfony_version',
                        'php_version',
                        'server_software',
                        'database_version',
                    ]))
                );

                if ($sandboxMode = ConfigManager::isSandboxMode()) {
                    $warningMessages[] = $module->l('Developer mode is active. You are using test environment.');
                } else {
                    $successMessages[] = $module->l('Production mode is active.');
                }

                if (!empty(ConfigManager::getApiKey())) {
                    try {
                        if (ApiClient::getInstance()->isShopAccountActive()) {
                            $successMessages[] = $module->l(sprintf(
                                '%s account is active.',
                                $sandboxMode ? 'Test' : 'Production'
                            ));
                        } else {
                            $warningMessages[] = $module->l(sprintf(
                                '%s account is not active.',
                                $sandboxMode ? 'Test' : 'Production'
                            ));
                        }
                    } catch (\Throwable $e) {
                        $errorMessages[] = $e->getMessage();

                        if ($e instanceof AuthorizationError || $e instanceof AccessDenied) {
                            $errorMessages[] = $module->l(sprintf(
                                'Invalid %s API key.',
                                $sandboxMode ? 'test' : 'production'
                            ));
                        }
                    }
                } else {
                    $errorMessages[] = $module->l(sprintf(
                        '%s API key not present.',
                        $sandboxMode ? 'Test' : 'Production'
                    ));
                }

                if (count($infoMessages)) {
                    $messages['description'] = implode('<br />', $infoMessages);
                }
                if (count($successMessages)) {
                    $messages['success'] = implode('<br />', $successMessages);
                }
                if (count($warningMessages)) {
                    $messages['warning'] = implode('<br />', $warningMessages);
                }
                if (count($errorMessages)) {
                    $messages['error'] = implode('<br />', $errorMessages);
                }

                break;
        }

        if (count($messages)) {
            $params['messages'] = $messages;
        }

        return $helper->generateForm(SettingsForm::getFormFields($module, $params));
    }

    private static function getHelperForm(
        \PaymentModule $module,
        string $submitAction,
        ?string $formTemplateDir = null,
        ?string $formTemplate = null
    ): \HelperForm {
        $helper = new \HelperForm();
        $language = (int) \Configuration::get('PS_LANG_DEFAULT');

        $helper->module = $module;
        $helper->name_controller = $module->name;
        $helper->token = \Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = \AdminController::$currentIndex . '&configure=' . $module->name;

        // Language
        $helper->default_form_language = $language;
        $helper->allow_employee_form_lang = $language;

        // Title and toolbar
        $helper->title = $module->displayName;
        $helper->show_toolbar = true; // false -> Remove toolbar.
        $helper->toolbar_scroll = true; // yes - > Toolbar is always visible at the top of the screen.
        $helper->submit_action = $submitAction;
        $helper->toolbar_btn = [
            'save' => [
                'desc' => $module->l('Save'),
                'href' => \AdminController::$currentIndex . '&configure=' . $module->name . '&save' . $module->name .
                    '&token=' . \Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => \AdminController::$currentIndex . '&token=' . \Tools::getAdminTokenLite('AdminModules'),
                'desc' => $module->l('Back to list'),
            ],
        ];

        if ($formTemplate !== null && $formTemplateDir !== null) {
            $helper->base_folder = $formTemplateDir;
            $helper->base_tpl = $formTemplate;
        }

        return $helper;
    }
}
