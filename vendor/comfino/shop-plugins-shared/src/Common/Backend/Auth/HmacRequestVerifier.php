<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Auth;

use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Common\Backend\Clock\ClockInterface;
use Comfino\Common\Backend\Clock\SystemClock;
use ComfinoExternal\Psr\Http\Message\ServerRequestInterface;

final class HmacRequestVerifier
{
    /**
     * @var int
     */
    private $maxClockSkewSeconds = self::MAX_CLOCK_SKEW_SECONDS;
    public const HEADER = 'CR-HMAC-Signature';
    public const MIN_API_KEY_LENGTH = 16;
    public const MAX_CLOCK_SKEW_SECONDS = 300;
    public const MAX_BODY_BYTES = 4096;

    private const SIGNATURE_PATTERN = '/^[0-9a-f]{64}$/';
    private const TENANT_KEY_PATTERN = '/^[A-Za-z0-9_.:-]{1,64}$/';

    private $apiKeys;

    /**
     * @var \Comfino\Common\Backend\Clock\ClockInterface
     */
    private $clock;

    /**
     * @param array<mixed> $apiKeys
     */
    public function __construct(
        array $apiKeys,
        ?ClockInterface $clock = null,
        int $maxClockSkewSeconds = self::MAX_CLOCK_SKEW_SECONDS
    ) {
        $this->maxClockSkewSeconds = $maxClockSkewSeconds;
        $this->apiKeys = array_values(array_unique(array_filter(
            $apiKeys,
            static function ($apiKey) {
                return is_string($apiKey) && strlen(trim($apiKey)) >= self::MIN_API_KEY_LENGTH;
            }
        )));
        $this->clock = $clock ?? new SystemClock();
    }

    /**
     * @return array{timestamp:
     * @throws AuthorizationError
     * @throws AccessDenied
     */
    public function verify(string $rawBody, ?string $signatureHeader): array
    {
        if ($signatureHeader === null || trim($signatureHeader) === '') {
            throw new AuthorizationError('Unauthorized request.');
        }

        $normalizedSignature = strtolower(trim($signatureHeader));

        if (preg_match(self::SIGNATURE_PATTERN, $normalizedSignature) !== 1) {
            throw new AccessDenied('Access denied.');
        }

        if (strlen($rawBody) > self::MAX_BODY_BYTES) {
            throw new AccessDenied('Access denied.');
        }

        if ($this->apiKeys === []) {
            throw new AccessDenied('Access denied.');
        }

        $matched = false;

        foreach ($this->apiKeys as $apiKey) {
            if (hash_equals(self::sign($rawBody, $apiKey), $normalizedSignature)) {
                $matched = true;
            }
        }

        if (!$matched) {
            throw new AccessDenied('Access denied.');
        }

        $decoded = json_decode($rawBody, true);

        $isJsonObject = is_array($decoded) && $decoded !== [] && array_keys($decoded) !== range(0, count($decoded) - 1);

        if (!$isJsonObject || !isset($decoded['timestamp']) || !is_int($decoded['timestamp'])) {
            throw new AccessDenied('Access denied.');
        }

        $tenantKey = $decoded['tenantKey'] ?? null;

        if ($tenantKey !== null && (!is_string($tenantKey) || preg_match(self::TENANT_KEY_PATTERN, $tenantKey) !== 1)) {
            throw new AccessDenied('Access denied.');
        }

        if (abs($this->clock->now() - $decoded['timestamp']) > $this->maxClockSkewSeconds) {
            throw new AccessDenied('Access denied.');
        }

        return ['timestamp' => $decoded['timestamp'], 'tenantKey' => $tenantKey];
    }

    /**
     * @return array{timestamp:
     * @throws AuthorizationError
     * @throws AccessDenied
     */
    public function verifyRequest(ServerRequestInterface $request): array
    {
        $header = $request->hasHeader(self::HEADER) ? $request->getHeader(self::HEADER)[0] ?? null : null;

        $body = $request->getBody();
        $rawBody = $body->getContents();
        $body->rewind();

        return $this->verify($rawBody, $header);
    }

    public static function sign(string $rawBody, string $apiKey): string
    {
        return hash_hmac('sha3-256', $rawBody, $apiKey);
    }

    public static function createRequestBody(?string $tenantKey = null, ?int $timestamp = null): string
    {
        return (string) json_encode(
            ['tenantKey' => $tenantKey, 'timestamp' => $timestamp ?? time()],
            JSON_UNESCAPED_SLASHES
        );
    }
}
