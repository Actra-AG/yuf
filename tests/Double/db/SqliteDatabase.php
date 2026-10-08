<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\db;

use actra\yuf\db\DbConnectionParameters;
use actra\yuf\db\DbQueryLogList;
use actra\yuf\db\FrameworkDb;

/**
 * A `FrameworkDb` on an in-memory SQLite database with a small `users` table, so that the real code of `FrameworkDb`
 * runs without a MySQL server.
 */
final class SqliteDatabase
{
    public static function create(?DbQueryLogList $queryLog = null): FrameworkDb
    {
        $db = new FrameworkDb(
            connectionParameters: new DbConnectionParameters(dsn: 'sqlite::memory:'),
            queryLog: $queryLog ?? new DbQueryLogList(),
        );
        $db->execute(
            sql: 'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NULL)',
        );
        $db->execute(sql: 'INSERT INTO users (id, name, age) VALUES (1, ?, 30), (2, ?, NULL), (3, ?, 41)', parameters: [
            'Anna',
            'Ben',
            'Cleo',
        ]);

        return $db;
    }
}
