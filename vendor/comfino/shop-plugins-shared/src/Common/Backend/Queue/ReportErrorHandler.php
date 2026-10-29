<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Queue;

use Comfino\Api\Serializer\Json;
use Comfino\Api\SerializerInterface;
use Comfino\Extended\Api\Client;
use Comfino\Extended\Api\Dto\Plugin\ErrorCategory;
use Comfino\Extended\Api\Dto\Plugin\ErrorSeverity;
use Comfino\Extended\Api\Dto\Plugin\OperationContext;
use Comfino\Extended\Api\Dto\Plugin\ShopPluginError;

final class ReportErrorHandler implements TenantAwareRetryableOperationHandlerInterface
{
    /**
     * @var Client|\Closure(?string):Client
     */
    private $client;
    /**
     * @var SerializerInterface
     */
    private $serializer;
    
    public const OPERATION_TYPE = 'report_error';

    /**
     * @param SerializerInterface $serializer
     */
    public function __construct($client, ?SerializerInterface $serializer = null)
    {
        $serializer = $serializer ?? new Json();
        $this->client = $client;
        $this->serializer = $serializer;
    }

    /**
     * @throws \Throwable
     */
    public function execute($payload): void
    {
        $this->executeForTenant($payload, null);
    }

    /**
     * @param string|null $tenantKey
     * @throws \Throwable
     * @throws \RuntimeException
     */
    public function executeForTenant($payload, $tenantKey): void
    {
        $this->resolveClient($tenantKey)->sendLoggedError(self::fromQueuePayload($payload, $this->serializer));
    }

    /**
     * @return array<string,
     * @param \Comfino\Extended\Api\Dto\Plugin\ShopPluginError $error
     * @param \Comfino\Api\SerializerInterface $serializer
     */
    public static function toQueuePayload($error, $serializer): array
    {
        return [
            'host' => $error->host,
            'platform' => $error->platform,
            'pluginVersion' => $error->pluginVersion,
            'platformVersion' => $error->platformVersion,
            'phpVersion' => $error->phpVersion,
            'category' => $error->category->value,
            'severity' => $error->severity->value,
            'context' => $error->context->value,
            'errorCode' => $error->errorCode,
            'errorMessage' => $error->errorMessage,
            'environment' => $serializer->serialize($error->environment),
            'apiEndpoint' => $error->apiEndpoint ?? '',
            'apiRequestUrl' => $error->apiRequestUrl ?? '',
            'apiRequest' => $error->apiRequest ?? '',
            'apiResponse' => $error->apiResponse ?? '',
            'stackTrace' => $error->stackTrace ?? '',
            'occurredAt' => $error->occurredAt !== null ? (string) $error->occurredAt : '',
        ];
    }

    /**
     * @param \Comfino\Api\SerializerInterface $serializer
     */
    public static function fromQueuePayload($data, $serializer): ShopPluginError
    {
        $rawEnv = (string) ($data['environment'] ?? '');
        $environment = $rawEnv !== '' ? $serializer->unserialize($rawEnv) : [];

        return new ShopPluginError(
            (string) ($data['host'] ?? ''),
            (string) ($data['platform'] ?? ''),
            (string) ($data['pluginVersion'] ?? ''),
            (string) ($data['platformVersion'] ?? ''),
            (string) ($data['phpVersion'] ?? ''),
            ErrorCategory::tryFrom((string) ($data['category'] ?? '')) ?? ErrorCategory::from(ErrorCategory::Other),
            ErrorSeverity::tryFrom((string) ($data['severity'] ?? '')) ?? ErrorSeverity::from(ErrorSeverity::Error),
            OperationContext::tryFrom((string) ($data['context'] ?? '')) ?? OperationContext::from(OperationContext::Unknown),
            (string) ($data['errorCode'] ?? ''),
            (string) ($data['errorMessage'] ?? ''),
            is_array($environment) ? $environment : [],
            ($data['apiEndpoint'] ?? '') !== '' ? (string) $data['apiEndpoint'] : null,
            ($data['apiRequestUrl'] ?? '') !== '' ? (string) $data['apiRequestUrl'] : null,
            ($data['apiRequest'] ?? '') !== '' ? (string) $data['apiRequest'] : null,
            ($data['apiResponse'] ?? '') !== '' ? (string) $data['apiResponse'] : null,
            ($data['stackTrace'] ?? '') !== '' ? (string) $data['stackTrace'] : null,
            ($data['occurredAt'] ?? '') !== '' ? (int) $data['occurredAt'] : null
        );
    }

    /**
     * @param string|null $tenantKey
     */
    private function resolveClient(?string $tenantKey): Client
    {
        if ($this->client instanceof Client) {
            return $this->client;
        }

        $client = ($this->client)($tenantKey);

        if (!$client instanceof Client) {
            throw new \RuntimeException(
                sprintf(
                    'The report_error client factory must return a %s, got %s (tenant: %s).',
                    Client::class,
                    get_debug_type($client),
                    $tenantKey ?? '-'
                )
            );
        }

        return $client;
    }
}
