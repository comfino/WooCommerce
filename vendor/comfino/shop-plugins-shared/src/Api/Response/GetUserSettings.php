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

        $flags = [];

        foreach ($deserializedResponseBody['flags'] as $flag) {
            $this->checkResponseType($flag, 'array', 'flags[]');
            $this->checkResponseStructure($flag, ['name', 'attributes']);
            $this->checkResponseType($flag['name'], 'string', 'flags[][name]');
            $this->checkResponseType($flag['attributes'], 'array', 'flags[][attributes]');

            $flags[$flag['name']] = $flag['attributes'];
        }

        $this->flags = $flags;
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
