<?php

namespace Comfino\Api;

use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ResponseValidationError;

/**
 * Request/response serializer interface.
 */
interface SerializerInterface
{
    /**
     * Serializes request data structure.
     *
     * @param mixed $requestData Request data structure to serialize.
     * @return string
     * @throws RequestValidationError
     */
    public function serialize($requestData): string;

    /**
     * Unserializes serialized response string.
     *
     * @param string $responseBody Encoded response body to unserialize.
     * @return mixed
     * @throws ResponseValidationError
     */
    public function unserialize($responseBody);
}
