<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Queue;

use Comfino\Api\Serializer\Json;
use Comfino\Api\SensitiveDataRedactor;
use Comfino\Common\Backend\Clock\ClockInterface;
use Comfino\Common\Backend\Clock\SystemClock;

final class SqlRetryQueueStorage implements RetryQueueStorageInterface
{
    /**
     * @var \Comfino\Common\Backend\Queue\QueueDatabaseAdapterInterface
     */
    private $db;
    public const LOCK_TTL_SECONDS = 120;
    public const LAST_ERROR_MAX_BYTES = 512;

    /**
     * @var \Comfino\Common\Backend\Clock\ClockInterface
     */
    private $clock;
    /**
     * @var \Comfino\Api\Serializer\Json
     */
    private $serializer;

    public function __construct(QueueDatabaseAdapterInterface $db, ?ClockInterface $clock = null)
    {
        $this->db = $db;
        $this->clock = $clock ?? new SystemClock();
        $this->serializer = new Json();
    }

    /**
     * @param \Comfino\Common\Backend\Queue\QueuedRequest $request
     */
    public function enqueue($request): void
    {
        $this->dbInsertIgnore($this->db->tableName(), [
            'operation_type' => $request->operationType,
            'payload' => $this->serializer->serialize($request->payload),
            'attempts' => $request->attempts,
            'dedup_key' => $request->dedupKey(),
            'tenant_key' => $request->tenantKey,
            'last_error' => $this->redactLastError($request->lastError),
            'created_at' => $request->createdAt,
            'available_at' => $request->availableAt,
        ]);
    }

    /**
     * @param int $limit
     * @param string|null $tenantKey
     * @param int|null $dueAt
     */
    public function peekBatch($limit, $tenantKey = null, $dueAt = null): array
    {
        if ($limit < 1) {
            return [];
        }

        $now = $this->clock->now();
        $table = $this->db->tableName();
        $conditions = $this->unlockedCondition($now);

        if ($dueAt !== null) {
            $conditions[] = sprintf('available_at <= %d', $dueAt);
        }

        if ($tenantKey !== null) {
            $conditions[] = sprintf('tenant_key = %s', $this->db->quote($tenantKey));
        }

        $where = implode(' AND ', $conditions);

        $candidateRows = $this->dbFetchAll(sprintf(
            'SELECT request_id FROM `%s` WHERE %s ORDER BY request_id LIMIT %d',
            $table,
            $where,
            $limit
        ));

        if ($candidateRows === []) {
            return [];
        }

        $ids = array_map(static function (array $row) : int {
            return (int) $row['request_id'];
        }, $candidateRows);
        $idList = implode(',', $ids);

        $this->dbExecute(sprintf(
            'UPDATE `%s` SET locked_at = %d WHERE request_id IN (%s) AND (%s)',
            $table,
            $now,
            $idList,
            implode(' AND ', $this->unlockedCondition($now))
        ));

        $claimedRows = $this->dbFetchAll(sprintf(
            'SELECT * FROM `%s` WHERE request_id IN (%s) AND locked_at = %d ORDER BY request_id',
            $table,
            $idList,
            $now
        ));

        return array_map([$this, 'hydrate'], $claimedRows);
    }

    /**
     * @param \Comfino\Common\Backend\Queue\QueuedRequest $request
     */
    public function update($request): void
    {
        if ($request->id === null) {
            throw new \InvalidArgumentException('Cannot update a queued request that has not been persisted.');
        }

        $this->dbExecute(sprintf(
            'UPDATE `%s` SET attempts = %d, last_error = %s, available_at = %d, locked_at = NULL WHERE request_id = %d',
            $this->db->tableName(),
            $request->attempts,
            $this->db->quote($this->redactLastError($request->lastError)),
            $request->availableAt,
            (int) $request->id
        ));
    }

    /**
     * @param \Comfino\Common\Backend\Queue\QueuedRequest $request
     */
    public function remove($request): void
    {
        if ($request->id === null) {
            return;
        }

        $this->dbExecute(sprintf('DELETE FROM `%s` WHERE request_id = %d', $this->db->tableName(), (int) $request->id));
    }

    /**
     * @param string|null $tenantKey
     */
    public function count($tenantKey = null): int
    {
        $where = $tenantKey !== null ? sprintf(' WHERE tenant_key = %s', $this->db->quote($tenantKey)) : '';

        $rows = $this->dbFetchAll(sprintf('SELECT COUNT(*) AS cnt FROM `%s`%s', $this->db->tableName(), $where));

        return (int) ($rows[0]['cnt'] ?? 0);
    }

    /**
     * @param int|null $dueAt
     */
    public function pendingTenantKeys($dueAt = null): array
    {
        $conditions = $this->unlockedCondition($this->clock->now());

        if ($dueAt !== null) {
            $conditions[] = sprintf('available_at <= %d', $dueAt);
        }

        $rows = $this->dbFetchAll(sprintf(
            'SELECT tenant_key, MIN(request_id) AS first_id FROM `%s` WHERE %s GROUP BY tenant_key ORDER BY first_id',
            $this->db->tableName(),
            implode(' AND ', $conditions)
        ));

        return array_map(
            static function (array $row): ?string {
                $tenantKey = $row['tenant_key'] ?? null;

                return $tenantKey === null || $tenantKey === '' ? null : (string) $tenantKey;
            },
            $rows
        );
    }

    /**
     * @return int
     * @param int $createdBefore
     */
    public function purgeOlderThan($createdBefore): int
    {
        return $this->dbExecute(sprintf('DELETE FROM `%s` WHERE created_at < %d', $this->db->tableName(), $createdBefore));
    }

    /**
     * @return string[]
     */
    private function unlockedCondition(int $now): array
    {
        return [sprintf('(locked_at IS NULL OR locked_at < %d)', $now - self::LOCK_TTL_SECONDS)];
    }

    private function redactLastError(?string $error): ?string
    {
        return $error !== null ? SensitiveDataRedactor::truncate(SensitiveDataRedactor::redactText($error), self::LAST_ERROR_MAX_BYTES) : null;
    }

    private function hydrate(array $row): QueuedRequest
    {
        $tenantKey = $row['tenant_key'] ?? null;

        return new QueuedRequest(
            (int) $row['request_id'],
            (string) $row['operation_type'],
            (array) $this->serializer->unserialize((string) $row['payload']),
            (int) $row['attempts'],
            (int) $row['created_at'],
            isset($row['last_error']) && $row['last_error'] !== null ? (string) $row['last_error'] : null,
            $tenantKey === null || $tenantKey === '' ? null : (string) $tenantKey,
            (int) $row['available_at']
        );
    }

    /**
     * @return list<array<string,
     */
    private function dbFetchAll(string $sql): array
    {
        try {
            return $this->db->fetchAll($sql);
        } catch (\Throwable $e) {
            throw new QueueStorageException('Queue storage read failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function dbExecute(string $sql): int
    {
        try {
            return $this->db->execute($sql);
        } catch (\Throwable $e) {
            throw new QueueStorageException('Queue storage write failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function dbInsertIgnore(string $table, array $row): ?int
    {
        try {
            return $this->db->insertIgnore($table, $row);
        } catch (\Throwable $e) {
            throw new QueueStorageException('Queue storage insert failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
