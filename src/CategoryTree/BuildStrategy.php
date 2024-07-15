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

namespace Comfino\CategoryTree;

use Comfino\Common\Shop\Product\CategoryTree\BuildStrategyInterface;
use Comfino\Common\Shop\Product\CategoryTree\Descriptor;
use Comfino\Common\Shop\Product\CategoryTree\Node;
use Comfino\Common\Shop\Product\CategoryTree\NodeIterator;

if (!defined('_PS_VERSION_')) {
    exit;
}

class BuildStrategy implements BuildStrategyInterface
{
    /** @var Descriptor */
    private $descriptor;

    public function build(): Descriptor
    {
        if ($this->descriptor !== null) {
            return $this->descriptor;
        }

        $this->descriptor = new Descriptor();
        $this->descriptor->index = [];

        $nodes = [];

        foreach (\Category::getNestedCategories() as $category) {
            $node = new Node($category['id_category'], $category['name']);

            if (!empty($category['children'])) {
                $childNodes = [];

                foreach ($category['children'] as $childCategory) {
                    $childNodes[] = $this->processCategory($node, $childCategory);
                }

                $node->setChildren(new NodeIterator($childNodes));
            }

            $nodes[] = $node;
            $this->descriptor->index[$node->getId()] = $node;
        }

        $this->descriptor->nodes = new NodeIterator($nodes);

        return $this->descriptor;
    }

    private function processCategory(Node $parentNode, array $category): Node
    {
        $node = new Node($category['id_category'], $category['name'], $parentNode);

        if (!empty($category['children'])) {
            $childNodes = [];

            foreach ($category['children'] as $childCategory) {
                $childNodes[] = $this->processCategory($node, $childCategory);
            }

            $node->setChildren(new NodeIterator($childNodes));
        }

        $this->descriptor->index[$node->getId()] = $node;

        return $node;
    }
}
