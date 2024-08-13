<?php

namespace Comfino;

/** Replacement of enum type to maintain source code compatibility with PHP 7.1 (workaround for Rector transpilation bug). */
abstract class Enum implements \JsonSerializable
{
    /**
     * @readonly
     * @var string
     */
    private $value;

    public function __construct(string $value, bool $strict = true)
    {
        if ($strict && !in_array($value, (new \ReflectionObject($this))->getConstants(), true)) {
            throw new \InvalidArgumentException("Value '$value' does not exist.");
        }

        $this->value = $value;
    }

    public static function values(): array
    {
        return array_values((new \ReflectionClass(static::class))->getConstants());
    }

    public static function names(): array
    {
        return array_keys((new \ReflectionClass(static::class))->getConstants());
    }

    /**
     * @param string $value
     * @param bool $strict
     */
    abstract public static function from($value, $strict = true): self;

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
