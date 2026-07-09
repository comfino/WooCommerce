<?php

/**
 * Comfino Payment Gateway for WooCommerce
 *
 * @package Comfino\Frontend
 * @author Artur Kozubski <akozubski@comperia.pl>
 * @copyright Copyright (c) 2026 Comfino by Comperia.pl S.A.
 * @license https://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link https://github.com/comfino/woocommerce
 */

namespace Comfino\Frontend;

use Comfino\Api\Dto\Plugin\ShopTheme;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce implementation of AbstractShopEnvironmentBuilder.
 *
 * WooCommerce has no Magento-style theme inheritance; the active WordPress theme (and its parent, for child themes)
 * is reported as the theme code/parent chain, and the family is resolved via the registered ThemeFamilyRules,
 * defaulting to 'storefront' (the classic jQuery-based WooCommerce stack) when no rule matches.
 *
 * PHP 7.1 compatible (hand-written, not Rector-built).
 */
class WooCommerceShopEnvironmentBuilder extends AbstractShopEnvironmentBuilder
{
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
            $code = (string) $theme->get_stylesheet();
            $parents = [];

            $parent = $theme->parent();

            if ($parent !== false && $parent !== null) {
                $parents[] = (string) $parent->get_stylesheet();
            }
        } catch (\Throwable $e) {
            return new ShopTheme('', 'storefront', []);
        }

        $family = $this->rules->resolveFamily(array_merge([$code], $parents));

        if ($family === 'custom') {
            $family = 'storefront';
        }

        return new ShopTheme($code, $family, $parents);
    }
}