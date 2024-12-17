<?php

namespace Comfino\View;

use Comfino\Main;

if (!defined('ABSPATH')) {
    exit;
}

final class TemplateManager
{
    public static function renderView(string $name, string $path, array $variables = [], bool $display = true): string
    {
        $templatePath = Main::getPluginDirectory() . '/views';

        if (!empty($path)) {
            $templatePath .= ('/' . trim($path, ' /'));
        }

        if ($display) {
            wc_get_template("$name.php", $variables, '', "$templatePath/");

            return '';
        }

        return wc_get_template_html("$name.php", $variables, '', "$templatePath/");
    }
}
