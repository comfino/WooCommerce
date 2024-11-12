<?php

namespace Comfino\Api;

interface HttpErrorExceptionInterface
{
    public function getUrl(): string;

    public function getRequestBody(): string;

    public function getResponseBody(): string;

    public function getStatusCode(): int;
}
