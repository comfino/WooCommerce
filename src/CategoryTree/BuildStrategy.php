<?php

namespace Comfino\CategoryTree;

use Comfino\Common\Shop\Product\Category;
use Comfino\Common\Shop\Product\CategoryManager;
use Comfino\Common\Shop\Product\CategoryTree\BuildStrategyInterface;
use Comfino\Common\Shop\Product\CategoryTree\Descriptor;

if (!defined('ABSPATH')) {
    exit;
}

class BuildStrategy implements BuildStrategyInterface
{
    /** @var Descriptor */
    private $descriptor;

    public function build(): Descriptor
    {
        if ($this->descriptor === null) {
            $this->descriptor = CategoryManager::buildCategoryTree($this->getNestedCategories());
        }

        return $this->descriptor;
    }

    /**
     * @return Category[]
     */
    private function getNestedCategories(): array
    {
        static $categories = null;

        if ($categories === null) {
            $categories = $this->processTreeNodes(get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name']));
        }

        return $categories;
    }

    /**
     * @param \WP_Term[] $treeNodes
     */
    private function processTreeNodes(array $treeNodes, int $parentId = 0): array
    {
        $categoryTree = [];

        foreach ($treeNodes as $node) {
            if ($node->parent === $parentId) {
                $categoryTree[] = new Category($node->term_id, $node->name, 0, $this->processTreeNodes($treeNodes, $node->term_id));
            }
        }

        return $categoryTree;
    }
}
