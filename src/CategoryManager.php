<?php

namespace Comfino;

if (!defined('ABSPATH')) {
    exit;
}

final class CategoryManager
{
    public static function getNestedCategories(): array
    {
        static $categories = null;

        if ($categories === null) {
            $categories = self::processTreeNodes(get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name']));
        }

        return $categories;
    }

    /**
     * @param \WP_Term[] $treeNodes
     */
    private static function processTreeNodes(array $treeNodes, int $parentId = 0): array
    {
        $categoryTree = [];

        foreach ($treeNodes as $node) {
            if ($node->parent === $parentId) {
                $categoryTreeNode = ['id' => $node->term_id, 'name' => $node->name];
                $childNodes = self::processTreeNodes($treeNodes, $node->term_id);

                if (count($childNodes)) {
                    $categoryTreeNode['children'] = $childNodes;
                }

                $categoryTree[] = $categoryTreeNode;
            }
        }

        return $categoryTree;
    }
}
