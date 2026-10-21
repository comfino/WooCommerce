<?php

namespace Comfino\Tests\Stub;

use Comfino\Api\Response\GetUserSettings;

/**
 * API client double: records calls, returns configured responses or throws configured exceptions, never does I/O.
 */
class StubApiClient extends \Comfino\Common\Api\Client
{
    /** @var array<string, int> */
    public $calls = [];
    /** @var string[] */
    public $cancelledOrderIds = [];
    /** @var \Throwable|null */
    public $cancelOrderException;
    /** @var array|\Throwable */
    public $userSettingsFlags = [];
    /** @var string[] */
    public $userSettingsHeaders = [];
    /** @var \Throwable|null */
    public $productTypesException;

    public function __construct()
    {
    }

    public function setCustomApiHost($host): void
    {
    }

    public function setApiKey($apiKey): void
    {
    }

    public function setApiLanguage($language): void
    {
    }

    public function enableSandboxMode(): void
    {
    }

    public function disableSandboxMode(): void
    {
    }

    public function getApiKey(): string
    {
        return 'TEST_API_KEY_0123456789';
    }

    public function getApiHost(): string
    {
        return 'https://api-ecommerce.comfino.pl';
    }

    public function cancelOrder($orderId): void
    {
        $this->recordCall('cancelOrder');

        if ($this->cancelOrderException !== null) {
            throw $this->cancelOrderException;
        }

        $this->cancelledOrderIds[] = (string) $orderId;
    }

    public function getProductTypes($listType): \Comfino\Api\Response\GetProductTypes
    {
        $this->recordCall('getProductTypes');

        if ($this->productTypesException !== null) {
            throw $this->productTypesException;
        }

        throw new \RuntimeException('No product types configured in the stub.');
    }

    public function getUserSettings(): GetUserSettings
    {
        $this->recordCall('getUserSettings');

        if ($this->userSettingsFlags instanceof \Throwable) {
            throw $this->userSettingsFlags;
        }

        $response = (new \ReflectionClass(GetUserSettings::class))->newInstanceWithoutConstructor();
        $response->flags = $this->userSettingsFlags;

        $headers = new \ReflectionProperty(\Comfino\Api\Response::class, 'headers');
        $headers->setAccessible(true);
        $headers->setValue($response, $this->userSettingsHeaders);

        return $response;
    }

    public function getCallCount(string $method): int
    {
        return $this->calls[$method] ?? 0;
    }

    private function recordCall(string $method): void
    {
        $this->calls[$method] = ($this->calls[$method] ?? 0) + 1;
    }
}
