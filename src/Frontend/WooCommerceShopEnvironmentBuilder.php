<?php

namespace Comfino\Frontend;

use Comfino\Api\Dto\Plugin\ShopTheme;
use Comfino\Platform\WooCommercePlatformInfo;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce implementation of AbstractShopEnvironmentBuilder.
 *
 * WooCommerce has no Magento-style theme inheritance; the active WordPress theme (and its parent, for child themes)
 * is reported as the theme code/parent chain, and the family is resolved via the registered ThemeFamilyRules, falling
 * back to 'blocks' for FSE/block themes (detected via wp_is_block_theme()) and 'storefront' (the classic jQuery-based
 * WooCommerce stack) for everything else, when no rule matches.
 */
class WooCommerceShopEnvironmentBuilder extends AbstractShopEnvironmentBuilder
{
    /**
     * Builds an instance wired with the plugin's standard platform info and theme-family rules. The single shared
     * construction path for every call site that needs the WooCommerce shop environment (widget config, telemetry
     * report, ...) so they all resolve the theme family identically.
     */
    public static function createDefault(): self
    {
        return new self(new WooCommercePlatformInfo(), self::createThemeRules());
    }

    private static function createThemeRules(): ThemeFamilyRules
    {
        $rules = new ThemeFamilyRules();

        $rules->register('storefront', static function (array $themeChain): bool {
            foreach ($themeChain as $theme) {
                if (strpos($theme, 'storefront') !== false) {
                    return true;
                }
            }

            return false;
        });

        return $rules;
    }

    /**
     * {@inheritDoc}
     */
    protected function getPlatformIdentifier(): string
    {
        return 'woocommerce';
    }

    /**
     * {@inheritDoc}
     */
    protected function getPlatformName(): string
    {
        return 'WooCommerce';
    }

    /**
     * {@inheritDoc}
     *
     * WooCommerce/WordPress has no commercial-edition concept.
     */
    protected function detectEdition(): ?string
    {
        return null;
    }

    /**
     * {@inheritDoc}
     *
     * Reads the active theme via wp_get_theme(); for child themes the parent template is added to the chain.
     */
    protected function detectTheme(): ShopTheme
    {
        if (!function_exists('wp_get_theme')) {
            return new ShopTheme('', 'storefront', []);
        }

        try {
            $theme = wp_get_theme();
            $code = $theme->get_stylesheet();
            $parents = [];

            $parent = $theme->parent();

            if ($parent !== false && $parent !== null) {
                $parents[] = $parent->get_stylesheet();
            }
        } catch (\Throwable $e) {
            return new ShopTheme('', 'storefront', []);
        }

        $family = $this->rules->resolveFamily(array_merge([$code], $parents));

        if ($family === 'custom') {
            $family = (function_exists('wp_is_block_theme') && wp_is_block_theme()) ? 'blocks' : 'storefront';
        }

        return new ShopTheme($code, $family, $parents);
    }
}
