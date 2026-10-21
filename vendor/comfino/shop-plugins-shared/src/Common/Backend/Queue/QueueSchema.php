<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Queue;

final class QueueSchema
{
    public const TABLE = 'comfino_request_queue';

    private const VALID_TABLE_NAME = '/^[A-Za-z0-9_]+$/';
    private const VALID_ENGINES = ['InnoDB', 'MyISAM'];

    /**
     * @param string $prefixedTable
     * @param string $engine
     * @param string|null $charset
     * @throws \InvalidArgumentException
     */
    public static function createTableSql(string $prefixedTable, string $engine, ?string $charset = null): string
    {
        self::assertValidTableName($prefixedTable);
        self::assertValidEngine($engine);

        $charsetClause = $charset !== null ? sprintf(' DEFAULT CHARSET=%s', self::quoteIdentifier($charset)) : '';

        return <<<SQL
CREATE TABLE IF NOT EXISTS `{$prefixedTable}` (
    `request_id`     INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `operation_type` VARCHAR(64)       NOT NULL,
    `payload`        TEXT              NOT NULL,
    `attempts`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `dedup_key`      VARCHAR(191)      NOT NULL,
    `tenant_key`     VARCHAR(64)       NULL,
    `last_error`     VARCHAR(512)      NULL,
    `created_at`     INT UNSIGNED      NOT NULL,
    `available_at`   INT UNSIGNED      NOT NULL DEFAULT 0,
    `locked_at`      INT UNSIGNED      NULL,
    PRIMARY KEY (`request_id`),
    UNIQUE KEY `comfino_request_queue_dedup` (`dedup_key`),
    KEY `comfino_request_queue_drain` (`tenant_key`, `available_at`, `request_id`),
    KEY `comfino_request_queue_created` (`created_at`)
) ENGINE={$engine}{$charsetClause};
SQL;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function dropTableSql(string $prefixedTable): string
    {
        self::assertValidTableName($prefixedTable);

        return "DROP TABLE IF EXISTS `{$prefixedTable}`;";
    }

    private static function assertValidTableName(string $tableName): void
    {
        if ($tableName === '' || preg_match(self::VALID_TABLE_NAME, $tableName) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid queue table name: "%s".', $tableName));
        }
    }

    private static function assertValidEngine(string $engine): void
    {
        if (!in_array($engine, self::VALID_ENGINES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid storage engine "%s"; expected one of: %s.', $engine, implode(', ', self::VALID_ENGINES)));
        }
    }

    private static function quoteIdentifier(string $identifier): string
    {
        if (preg_match(self::VALID_TABLE_NAME, $identifier) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid charset name: "%s".', $identifier));
        }

        return $identifier;
    }
}
