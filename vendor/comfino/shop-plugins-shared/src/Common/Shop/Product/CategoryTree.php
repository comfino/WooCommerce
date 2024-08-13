<?php

namespace Comfino\Common\Shop\Product;

use Comfino\Common\Shop\Product\CategoryTree\BuildStrategyInterface;
use Comfino\Common\Shop\Product\CategoryTree\Node;
use Comfino\Common\Shop\Product\CategoryTree\NodeIterator;

final class CategoryTree
{
    /**
     * @readonly
     * @var \Comfino\Common\Shop\Product\CategoryTree\BuildStrategyInterface
     */
    private $buildStrategy;
    /**
     * @var \Comfino\Common\Shop\Product\CategoryTree\NodeIterator|null
     */
    private $nodes;

    /** @var Node[]|null */
    private $index;

    public function __construct(BuildStrategyInterface $buildStrategy)
    {
        $this->buildStrategy = $buildStrategy;
    }

    public function getNodes(): NodeIterator
    {
        if ($this->nodes === null) {
            $treeDescriptor = $this->buildStrategy->build();

            $this->nodes = $treeDescriptor->nodes;
            $this->index = $treeDescriptor->index;
        }

        return $this->nodes;
    }

    /**
     * @return int[]
     */
    public function getNodeIds(?Node $rootNode = null): array
    {
        if (!count($this->getNodes())) {
            return [];
        }

        if ($this->index !== null) {
            return array_keys($this->index);
        }

        if ($rootNode === null) {
            $nodeIds = array_map(static function (Node $node) : int {
                return $node->getId();
            }, iterator_to_array($this->nodes));
            $subNodeIds = [];

            foreach ($this->nodes as $node) {
                if ($node->hasChildren()) {
                    $subNodeIds[] = $this->getNodeIds($node);
                }
            }

            $nodeIds = array_merge($nodeIds, ...$subNodeIds);
        } else {
            $nodeIds = [$rootNode->getId()];

            if ($rootNode->hasChildren()) {
                $subNodeIds = [];

                foreach ($rootNode->getChildren() as $node) {
                    $subNodeIds[] = $this->getNodeIds($node);
                }

                $nodeIds = array_merge($nodeIds, ...$subNodeIds);
            }
        }

        return $nodeIds;
    }

    /**
     * @return int[]
     */
    public function getPathNodeIds(NodeIterator $nodes): array
    {
        return array_map(static function (Node $node) : int {
            return $node->getId();
        }, iterator_to_array($nodes));
    }

    public function getNodeById(int $id, ?Node $rootNode = null): ?Node
    {
        if ($this->index !== null && array_key_exists($id, $this->index)) {
            return $this->index[$id];
        }

        if ($this->index === null) {
            $this->index = [];
        }

        if ($rootNode === null) {
            foreach ($this->getNodes() as $node) {
                $this->index[$node->getId()] = $node;

                if ($node->getId() === $id) {
                    return $node;
                }
            }

            foreach ($this->getNodes() as $node) {
                if ($node->hasChildren()) {
                    foreach ($node->getChildren() as $childNode) {
                        $this->index[$childNode->getId()] = $childNode;

                        if ($childNode->getId() === $id) {
                            return $childNode;
                        }
                    }

                    foreach ($node->getChildren() as $childNode) {
                        if (($foundNode = $this->getNodeById($id, $childNode)) !== null) {
                            return $foundNode;
                        }
                    }
                }
            }
        } else {
            $this->index[$rootNode->getId()] = $rootNode;

            if ($rootNode->getId() === $id) {
                return $rootNode;
            }

            if ($rootNode->hasChildren()) {
                foreach ($rootNode->getChildren() as $childNode) {
                    $this->index[$childNode->getId()] = $childNode;

                    if ($childNode->getId() === $id) {
                        return $childNode;
                    }
                }

                foreach ($rootNode->getChildren() as $childNode) {
                    if (($foundNode = $this->getNodeById($id, $childNode)) !== null) {
                        return $foundNode;
                    }
                }
            }
        }

        $this->index[$id] = null;

        return null;
    }
}
