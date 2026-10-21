<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Queue;

use Comfino\Api\Serializer\Json;

final class QueuedRequest
{
    /**
     * @var int|string|null
     */
    public $id;
    /**
     * @var string
     */
    public $operationType;
    /**
     * @var array<string,
     */
    public $payload;
    /**
     * @var int
     */
    public $attempts;
    /**
     * @var int
     */
    public $createdAt;
    /**
     * @var string|null
     */
    public $lastError;
    /**
     * @var string|null
     */
    public $tenantKey;
    /**
     * @var int
     */
    public $availableAt = 0;
    /**
     * @param int|string|null $id
     * @param string $operationType
     * @param int $attempts
     * @param int $createdAt
     * @param string|null $lastError
     * @param string|null $tenantKey
     * @param int $availableAt
     */
    public function __construct(
        $id,
        string $operationType,
        array $payload,
        int $attempts,
        int $createdAt,
        ?string $lastError = null,
        ?string $tenantKey = null,
        int $availableAt = 0
    ) {
        $this->id = $id;
        $this->operationType = $operationType;
        $this->payload = $payload;
        $this->attempts = $attempts;
        $this->createdAt = $createdAt;
        $this->lastError = $lastError;
        $this->tenantKey = $tenantKey;
        $this->availableAt = $availableAt;
        if ($operationType === '') {
            throw new \InvalidArgumentException('Queued request operation type must not be empty.');
        }
    }

    /**
     * @param string $operationType
     * @param int $createdAt
     * @param string|null $tenantKey
     */
    public static function create(string $operationType, array $payload, int $createdAt, ?string $tenantKey = null): self
    {
        return new self(null, $operationType, $payload, 0, $createdAt, null, $tenantKey, 0);
    }

    /**
     * @param int|string $id
     */
    public function withId($id): self
    {
        return new self(
            $id,
            $this->operationType,
            $this->payload,
            $this->attempts,
            $this->createdAt,
            $this->lastError,
            $this->tenantKey,
            $this->availableAt
        );
    }

    /**
     * @param string $error
     * @param int $availableAt
     */
    public function withAttemptFailure(string $error, int $availableAt = 0): self
    {
        return new self(
            $this->id,
            $this->operationType,
            $this->payload,
            $this->attempts + 1,
            $this->createdAt,
            $error,
            $this->tenantKey,
            $availableAt
        );
    }

    /**
     * @param string $error
     * @param int $availableAt
     */
    public function deferredTo(string $error, int $availableAt): self
    {
        return new self(
            $this->id,
            $this->operationType,
            $this->payload,
            $this->attempts,
            $this->createdAt,
            $error,
            $this->tenantKey,
            $availableAt
        );
    }

    /**
     * @param int $now
     */
    public function isDue(int $now): bool
    {
        return $this->availableAt <= $now;
    }

    public function dedupKey(): string
    {
        return ($this->tenantKey ?? '-') . ':' . $this->operationType . ':' .
            sha1((new Json())->serialize($this->payload));
    }

    /**
     * @return array{
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'operationType' => $this->operationType,
            'payload' => $this->payload,
            'attempts' => $this->attempts,
            'createdAt' => $this->createdAt,
            'lastError' => $this->lastError,
            'tenantKey' => $this->tenantKey,
            'availableAt' => $this->availableAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? null,
            (string) ($data['operationType'] ?? ''),
            (array) ($data['payload'] ?? []),
            (int) ($data['attempts'] ?? 0),
            (int) ($data['createdAt'] ?? 0),
            isset($data['lastError']) ? (string) $data['lastError'] : null,
            isset($data['tenantKey']) && $data['tenantKey'] !== '' ? (string) $data['tenantKey'] : null,
            (int) ($data['availableAt'] ?? 0)
        );
    }
}
