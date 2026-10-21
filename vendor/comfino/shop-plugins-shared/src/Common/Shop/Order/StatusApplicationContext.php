<?php

declare(strict_types=1);

namespace Comfino\Common\Shop\Order;

final class StatusApplicationContext
{
    /**
     * @var string
     */
    private $scope = '';
    
    private static $instances = [];

    /**
     * @var int
     */
    private $depth = 0;

    /**
     * @param string $scope
     * @return self
     */
    public static function forScope(string $scope = ''): self
    {
        return self::$instances[$scope] = self::$instances[$scope] ?? new self($scope);
    }

    /**
     * @param string|null $scope
     */
    public static function reset(?string $scope = null): void
    {
        if ($scope === null) {
            self::$instances = [];
        } else {
            unset(self::$instances[$scope]);
        }
    }

    /**
     * @param string|null $scope
     */
    public static function isActive(?string $scope = null): bool
    {
        if ($scope !== null) {
            return isset(self::$instances[$scope]) && self::$instances[$scope]->isActiveInScope();
        }

        foreach (self::$instances as $instance) {
            if ($instance->isActiveInScope()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $scope
     */
    private function __construct(string $scope = '')
    {
        $this->scope = $scope;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    /**
     * @template T
     * @return T
     */
    public function apply(callable $callback)
    {
        $this->depth++;

        try {
            return $callback();
        } finally {
            $this->depth--;
        }
    }

    public function isActiveInScope(): bool
    {
        return $this->depth > 0;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }
}
