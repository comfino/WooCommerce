<?php

declare(strict_types=1);

namespace Comfino\Api\Response;

class GetUserSettings extends Base
{
    public $flags;

    /**
     * @param mixed[]|string|bool|null|float|int $deserializedResponseBody
     */
    protected function processResponseBody($deserializedResponseBody): void
    {
        $this->checkResponseType($deserializedResponseBody, 'array');
        $this->checkResponseStructure($deserializedResponseBody, ['flags']);
        $this->checkResponseType($deserializedResponseBody['flags'], 'array', 'flags');

        foreach ($deserializedResponseBody['flags'] as $flagName => $attributes) {
            $this->checkResponseType($attributes, 'array', "flags.$flagName");
        }

        $this->flags = $deserializedResponseBody['flags'];
    }

    /**
     * @param string $flag
     */
    public function hasFlag($flag): bool
    {
        return array_key_exists($flag, $this->flags);
    }

    /**
     * @return array<string,
     * @param string $flag
     */
    public function getFlagAttributes($flag): array
    {
        return $this->flags[$flag] ?? [];
    }
}
