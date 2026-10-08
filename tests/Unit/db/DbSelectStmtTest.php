<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\db;

use actra\yuf\db\DbQueryLogList;
use actra\yuf\db\DbRowCountException;
use actra\yuf\db\DbRuntimeException;
use actra\yuf\db\DbSelectStmt;
use Override;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Uses an in-memory SQLite database: FrameworkDb itself builds a MySQL connection, but selectRows()/selectRow() only
 * delegate to DbSelectStmt.
 */
final class DbSelectStmtTest extends TestCase
{
    private PDO $pdo;

    #[Override]
    protected function setUp(): void
    {
        $this->pdo = new PDO(dsn: 'sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec(
            statement: 'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NULL)',
        );
        $this->pdo->exec(
            statement: "INSERT INTO users (id, name, age) VALUES (1, 'Anna', 30), (2, 'Ben', NULL)",
        );
    }

    private function stmt(string $sql): DbSelectStmt
    {
        $pdoStatement = $this->pdo->prepare(query: $sql);
        $this->assertInstanceOf(PDOStatement::class, $pdoStatement);

        return new DbSelectStmt(pdoStatement: $pdoStatement);
    }

    public function testFetchRowsReturnsTypedRows(): void
    {
        $rows = $this->stmt(sql: 'SELECT id, name, age FROM users ORDER BY id')->executeAndFetchRows(parameters: []);

        $this->assertCount(2, $rows);
        $this->assertSame(1, $rows[0]->getInt(column: 'id'));
        $this->assertSame('Anna', $rows[0]->getString(column: 'name'));
        $this->assertSame(30, $rows[0]->getNullableInt(column: 'age'));
        $this->assertNull($rows[1]->getNullableInt(column: 'age'));
    }

    public function testFetchRowsBindsParameters(): void
    {
        $rows = $this->stmt(sql: 'SELECT name FROM users WHERE id = ?')->executeAndFetchRows(parameters: [2]);

        $this->assertCount(1, $rows);
        $this->assertSame('Ben', $rows[0]->getString(column: 'name'));
    }

    public function testFetchRowsReturnsEmptyListWithoutResult(): void
    {
        $this->assertSame(
            [],
            $this->stmt(sql: 'SELECT id FROM users WHERE id = 99')->executeAndFetchRows(parameters: []),
        );
    }

    public function testFetchRowReturnsTheSingleRow(): void
    {
        $row = $this->stmt(sql: 'SELECT name FROM users WHERE id = ?')->executeAndFetchRow(parameters: [1]);

        $this->assertNotNull($row);
        $this->assertSame('Anna', $row->getString(column: 'name'));
    }

    public function testFetchRowReturnsNullWithoutResult(): void
    {
        $this->assertNull($this->stmt(sql: 'SELECT id FROM users WHERE id = 99')->executeAndFetchRow(parameters: []));
    }

    public function testFetchRowThrowsOnMoreThanOneRow(): void
    {
        $this->expectException(DbRowCountException::class);
        $this->expectExceptionMessageIsOrContains('returned 2 rows');
        $this->stmt(sql: 'SELECT id FROM users')->executeAndFetchRow(parameters: []);
    }

    public function testExecutionErrorsAreWrappedWithTheSql(): void
    {
        $this->expectException(DbRuntimeException::class);
        $this->expectExceptionMessageIsOrContains('SQL-String: "INSERT INTO users (id, name) VALUES (5, NULL)"');
        // Fails on execute (NOT NULL constraint)
        $this->stmt(sql: 'INSERT INTO users (id, name) VALUES (5, NULL)')->executeAndFetchRows(parameters: []);
    }

    public function testExecuteAndFetchReturnsObjects(): void
    {
        $rows = $this->stmt(sql: 'SELECT id, name FROM users WHERE id = ?')->executeAndFetch(parameters: [2]);

        $this->assertCount(1, $rows);
        $this->assertSame('Ben', $rows[0]->name);
    }

    public function testExecutionsAreLoggedWithTheGivenLog(): void
    {
        $queryLog = new DbQueryLogList();
        $pdoStatement = $this->pdo->prepare(query: 'SELECT name FROM users WHERE id = ?');
        $this->assertInstanceOf(PDOStatement::class, $pdoStatement);
        $statement = new DbSelectStmt(pdoStatement: $pdoStatement, queryLog: $queryLog);

        $statement->executeAndFetchRows(parameters: [1]);
        $statement->executeAndFetch(parameters: [2]);

        $items = $queryLog->getItems();
        $this->assertCount(2, $items);
        $this->assertSame('SELECT name FROM users WHERE id = ?', $items[0]->sqlQuery);
        $this->assertSame([2], $items[1]->params);
    }

    public function testExecutionsAreNotLoggedWithoutLog(): void
    {
        $rows = $this->stmt(sql: 'SELECT name FROM users')->executeAndFetchRows(parameters: []);

        $this->assertCount(2, $rows);
    }

    public function testParametersOfAFailedExecutionAreNotInTheMessage(): void
    {
        try {
            $this->stmt(sql: 'INSERT INTO users (id, name) VALUES (?, ?)')
                ->executeAndFetchRows(parameters: [1, 'anna@example.com']);
            DbSelectStmtTest::fail('Expected DbRuntimeException');
        } catch (DbRuntimeException $exception) {
            $this->assertStringNotContainsString('anna@example.com', $exception->getMessage());
        }
    }
}
