<?php

namespace Comfino\View;

use Comfino\Main;

if (!defined('ABSPATH')) {
    exit;
}

final class TemplateManager
{
    public static function renderView(string $name, string $path, array $variables = []): string
    {
        $templatePath = Main::getPluginDirectory() . '/views/templates';

        if (!empty($path)) {
            $templatePath .= ('/' . trim($path, ' /'));
        }

        return wc_get_template_html("$name.php", $variables, '', "$templatePath/");
    }
}
