<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\db;

use actra\yuf\db\DbConnectionParameters;
use actra\yuf\db\DbRow;
use actra\yuf\db\FrameworkDb;
use Override;

/**
 * The `users` table of `SqliteDatabase` on a connection that counts the calls of `selectRow()` (the count query of a
 * `DbQuery` uses it).
 */
final class SelectRowCountingDb extends FrameworkDb
{
    public private(set) int $selectRowCalls = 0;

    public function __construct()
    {
        parent::__construct(connectionParameters: new DbConnectionParameters(dsn: 'sqlite::memory:'));
        $this->execute(sql: 'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NULL)');
        $this->execute(
            sql: 'INSERT INTO users (id, name, age) VALUES (1, ?, 30), (2, ?, NULL), (3, ?, 41)',
            parameters: ['Anna', 'Ben', 'Cleo'],
        );
    }

    #[Override]
    public function selectRow(string $sql, array $parameters = [], bool $logQuery = false): ?DbRow
    {
        $this->selectRowCalls++;

        return parent::selectRow(sql: $sql, parameters: $parameters, logQuery: $logQuery);
    }
}
