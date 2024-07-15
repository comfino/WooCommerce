<?php

namespace Comfino;

use PHPUnit\Framework\TestCase;

class TestedEnum extends Enum
{
    public const ONE = '1';
    public const TWO = '2';
    public const THREE = '3';

    /**
     * @param string $value
     * @param bool $strict
     */
    public static function from($value, $strict = true): \Comfino\Enum
    {
        return new self($value, $strict);
    }
}

class EnumTest extends TestCase
{
    public function testConstruct(): void
    {
        $enum1 = new TestedEnum(TestedEnum::ONE);
        $enum2 = new TestedEnum(TestedEnum::TWO);
        $enum3 = new TestedEnum(TestedEnum::THREE);

        $this->assertEquals(TestedEnum::ONE, (string) $enum1);
        $this->assertEquals(TestedEnum::TWO, (string) $enum2);
        $this->assertEquals(TestedEnum::THREE, (string) $enum3);

        $enum1 = TestedEnum::from(TestedEnum::ONE);
        $enum2 = TestedEnum::from(TestedEnum::TWO);
        $enum3 = TestedEnum::from(TestedEnum::THREE);

        $this->assertEquals(TestedEnum::ONE, (string) $enum1);
        $this->assertEquals(TestedEnum::TWO, (string) $enum2);
        $this->assertEquals(TestedEnum::THREE, (string) $enum3);
    }

    public function testEnumMethods(): void
    {
        $this->assertEquals([TestedEnum::ONE, TestedEnum::TWO, TestedEnum::THREE], TestedEnum::values());
        $this->assertEquals(['ONE', 'TWO', 'THREE'], TestedEnum::names());
        $this->assertJsonStringEqualsJsonString(
            '["1","2"]',
            json_encode([new TestedEnum(TestedEnum::ONE), new TestedEnum(TestedEnum::TWO)])
        );
    }

    public function testError(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TestedEnum::from('InvalidEnum');
    }

    public function testStrictModeOff(): void
    {
        $this->assertEquals('InvalidEnum', (string) TestedEnum::from('InvalidEnum', false));
        $this->assertJsonStringEqualsJsonString('"InvalidEnum"', '"' . TestedEnum::from('InvalidEnum', false)->jsonSerialize() . '"');
    }
}
