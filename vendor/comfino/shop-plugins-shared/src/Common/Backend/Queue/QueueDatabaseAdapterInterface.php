<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Queue;

interface QueueDatabaseAdapterInterface
{
    /**
     * @return list<array<string,
     * @param string $sql
     */
    public function fetchAll($sql): array;

    /**
     * @return int
     * @param string $sql
     */
    public function execute($sql): int;

    /**
     * @return int|null
     * @param string $table
     */
    public function insertIgnore($table, $row): ?int;

    /**
     * @param string|int|null $value
     */
    public function quote($value): string;

    public function tableName(): string;
}
