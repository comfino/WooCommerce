<?php

namespace Comfino\Common\Shop\Product\CategoryTree;

final class Node
{
    /**
     * @var int
     */
    private $id;
    /**
     * @var string
     */
    private $name;
    /**
     * @var \Comfino\Common\Shop\Product\CategoryTree\Node|null
     */
    private $parent;
    /**
     * @var \Comfino\Common\Shop\Product\CategoryTree\NodeIterator|null
     */
    private $children;

    public function __construct(int $id, string $name, ?Node $parent = null, ?NodeIterator $children = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->parent = $parent;
        $this->children = $children;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParent(): ?Node
    {
        return $this->parent;
    }

    public function setParent(?Node $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): ?NodeIterator
    {
        return $this->children;
    }

    public function setChildren(NodeIterator $children): void
    {
        $this->children = $children;
    }

    public function isRoot(): bool
    {
        return $this->parent === null;
    }

    public function isLeaf(): bool
    {
        return !$this->hasChildren();
    }

    public function isParentOf(Node $node): bool
    {
        return $this === $node->getParent();
    }

    public function isChildOf(Node $node): bool
    {
        return $node === $this->parent;
    }

    public function isAncestorOf(Node $node): bool
    {
        if ($this->isParentOf($node)) {
            return true;
        }

        if ($this->isLeaf()) {
            return false;
        }

        foreach ($this->children as $childNode) {
            if ($childNode->isAncestorOf($node)) {
                return true;
            }
        }

        return false;
    }

    public function isDescendantOf(Node $node): bool
    {
        if ($this->isChildOf($node)) {
            return true;
        }

        if ($this->isRoot()) {
            return false;
        }

        $parentNode = $this->parent;

        while ($parentNode !== null) {
            if ($parentNode === $node) {
                return true;
            }

            $parentNode = $parentNode->getParent();
        }

        return false;
    }

    public function hasChildren(): bool
    {
        return $this->children !== null && $this->children->count() !== 0;
    }

    public function getPathToRoot(): NodeIterator
    {
        $nodes = [];
        $node = $this;

        do {
            $nodes[] = $node;
        } while (($node = $node->getParent()) !== null);

        return new NodeIterator($nodes);
    }
}
