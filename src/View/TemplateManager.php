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

use const Comfino\_MODULE_DIR_;
use const Comfino\COMFINO_PS_17;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class TemplateManager
{
    public static function renderModuleView(
        \PaymentModule $module,
        string $name,
        string $path,
        array $variables = []
    ): string {
        $templatePath = 'views/templates';

        if (!empty($path)) {
            $templatePath .= ('/' . trim($path, ' /'));
        }

        $templatePath .= "/$name.tpl";

        if (!empty($variables)) {
            \Context::getContext()->smarty->assign($variables);
        }

        if (method_exists($module, 'fetch')) {
            return $module->fetch("module:$module->name/$templatePath");
        }

        return $module->display(_MODULE_DIR_ . "$module->name/$module->name.php", $templatePath);
    }

    public static function renderControllerView(
        \ModuleFrontController $frontController,
        string $name,
        string $path,
        array $variables = []
    ): void {
        $templatePath = 'views/templates';

        if (!empty($path)) {
            $templatePath .= ('/' . trim($path, ' /'));
        }

        $templatePath .= "/$name.tpl";

        if (!empty($variables)) {
            \Context::getContext()->smarty->assign($variables);
        }

        if (COMFINO_PS_17) {
            $frontController->setTemplate("module:{$frontController->module->name}/$templatePath");
        } else {
            $frontController->setTemplate("$name.tpl");
        }
    }
}
