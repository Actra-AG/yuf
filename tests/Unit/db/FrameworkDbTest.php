<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\db;

use actra\yuf\db\DbConnectionParameters;
use actra\yuf\db\DbQueryLogList;
use actra\yuf\db\DbRow;
use actra\yuf\db\DbRowCountException;
use actra\yuf\db\DbRuntimeException;
use actra\yuf\db\FrameworkDb;
use actra\yuf\tests\Double\db\SqliteDatabase;
use actra\yuf\tests\Double\db\SteppingClock;
use InvalidArgumentException;
use LogicException;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Runs the code of `FrameworkDb` on an in-memory SQLite database. Only the MySQL specific connection setup
 * (`DbConnectionParameters::forMysql()`) is tested without a server, see `DbConnectionParametersTest`.
 */
final class FrameworkDbTest extends TestCase
{
    public function testSelectReturnsObjectsWithNativeTypes(): void
    {
        $rows = SqliteDatabase::create()->select(sql: 'SELECT id, name, age FROM users ORDER BY id');

        $this->assertCount(3, $rows);
        $this->assertSame(1, $rows[0]->id);
        $this->assertSame('Anna', $rows[0]->name);
        $this->assertNull($rows[1]->age);
    }

    public function testSelectBindsParameters(): void
    {
        $rows = SqliteDatabase::create()->select(
            sql: 'SELECT name FROM users WHERE id = ? OR age > ?',
            parameters: [2, 40],
        );

        $this->assertSame(['Ben', 'Cleo'], array_column(array: $rows, column_key: 'name'));
    }

    public function testBoundValuesAreNeverPartOfTheSql(): void
    {
        $db = SqliteDatabase::create();

        $rows = $db->select(sql: 'SELECT name FROM users WHERE name = ?', parameters: ["Anna' OR '1'='1"]);
        $db->execute(sql: 'INSERT INTO users (id, name) VALUES (?, ?)', parameters: [9, "x'); DROP TABLE users; --"]);

        $this->assertSame([], $rows);
        $this->assertCount(4, $db->select(sql: 'SELECT id FROM users'));
        $this->assertSame(
            "x'); DROP TABLE users; --",
            $db->selectRow(sql: 'SELECT name FROM users WHERE id = 9')?->getString(column: 'name'),
        );
    }

    public function testSelectRowsReturnsTypedRows(): void
    {
        $rows = SqliteDatabase::create()->selectRows(sql: 'SELECT id, age FROM users ORDER BY id');

        $this->assertSame(
            [1, 2, 3],
            array_map(callback: static fn(DbRow $row): int => $row->getInt(column: 'id'), array: $rows),
        );
        $this->assertSame(
            [30, null, 41],
            array_map(callback: static fn(DbRow $row): ?int => $row->getNullableInt(column: 'age'), array: $rows),
        );
    }

    public function testSelectRowReturnsNullWithoutRow(): void
    {
        $row = SqliteDatabase::create()->selectRow(sql: 'SELECT id FROM users WHERE id = ?', parameters: [99]);

        $this->assertNull($row);
    }

    public function testSelectRowThrowsForMoreThanOneRow(): void
    {
        $this->expectException(DbRowCountException::class);

        SqliteDatabase::create()->selectRow(sql: 'SELECT id FROM users');
    }

    public function testPrepareSelectCanBeExecutedRepeatedly(): void
    {
        $statement = SqliteDatabase::create()->prepareSelect(query: 'SELECT name FROM users WHERE id = ?');

        $this->assertSame('Anna', $statement->executeAndFetchRow(parameters: [1])?->getString(column: 'name'));
        $this->assertSame('Ben', $statement->executeAndFetchRow(parameters: [2])?->getString(column: 'name'));
    }

    public function testExecuteReturnsTheExecutedStatement(): void
    {
        $db = SqliteDatabase::create();

        $statement = $db->execute(sql: 'UPDATE users SET age = ? WHERE age IS NULL', parameters: [18]);

        $this->assertSame(1, $statement->rowCount());
        $this->assertSame(18, $db->selectRow(sql: 'SELECT age FROM users WHERE id = 2')?->getInt(column: 'age'));
    }

    public function testGetLastInsertIdReturnsTheGeneratedId(): void
    {
        $db = SqliteDatabase::create();

        $db->execute(sql: 'INSERT INTO users (name) VALUES (?)', parameters: ['Dora']);

        $this->assertSame(4, $db->getLastInsertId());
    }

    public function testGetLastInsertIdIsZeroWithoutInsert(): void
    {
        $db = new FrameworkDb(connectionParameters: new DbConnectionParameters(dsn: 'sqlite::memory:'));

        $this->assertSame(0, $db->getLastInsertId());
    }

    public function testLastInsertIdPointsToGetLastInsertId(): void
    {
        $db = SqliteDatabase::create();
        $db->execute(sql: 'INSERT INTO users (name) VALUES (?)', parameters: ['Dora']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('Use FrameworkDb::getLastInsertId(): int instead of lastInsertId().');

        $db->lastInsertId();
    }

    public function testInvalidSqlThrowsADbRuntimeExceptionWithTheSql(): void
    {
        $db = SqliteDatabase::create();

        try {
            $db->select(sql: 'SELECT nothing FROM nowhere');
            FrameworkDbTest::fail('Expected DbRuntimeException');
        } catch (DbRuntimeException $exception) {
            $this->assertStringContainsString('SQL-String: "SELECT nothing FROM nowhere"', $exception->getMessage());
            $this->assertInstanceOf(PDOException::class, $exception->getPrevious());
        }
    }

    public function testFailingExecutionDoesNotRevealBoundValues(): void
    {
        $db = SqliteDatabase::create();

        try {
            $db->execute(sql: 'INSERT INTO users (id, name) VALUES (?, ?)', parameters: [1, 'anna@example.com']);
            FrameworkDbTest::fail('Expected DbRuntimeException');
        } catch (DbRuntimeException $exception) {
            $this->assertStringContainsString('SQL-Parameters: 2 bound values', $exception->getMessage());
            $this->assertStringNotContainsString('anna@example.com', $exception->getMessage());
        }
    }

    public function testConnectionOptionsCannotSwitchOffTheExceptions(): void
    {
        $db = new FrameworkDb(
            connectionParameters: new DbConnectionParameters(
                dsn: 'sqlite::memory:',
                options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT],
            ),
        );

        $this->assertSame(PDO::ERRMODE_EXCEPTION, $db->getAttribute(PDO::ATTR_ERRMODE));
    }

    public function testFailedConnectionDoesNotCarryThePassword(): void
    {
        try {
            new FrameworkDb(
                connectionParameters: new DbConnectionParameters(
                    dsn: 'sqlite:/nonexistent-directory/test.db',
                    userName: 'someUser',
                    password: 'topSecretPassword',
                ),
            );
            FrameworkDbTest::fail('Expected PDOException');
        } catch (PDOException $exception) {
            $this->assertStringNotContainsString('topSecretPassword', $exception->getMessage());
            $this->assertStringNotContainsString('topSecretPassword', $exception->getTraceAsString());
            $this->assertNull($exception->getPrevious());
        }
    }

    public function testCommittedTransactionChangesAreKept(): void
    {
        $db = SqliteDatabase::create();

        $this->assertTrue($db->beginTransaction());
        $db->execute(sql: 'DELETE FROM users WHERE id = ?', parameters: [1]);
        $this->assertTrue($db->commit());

        $this->assertCount(2, $db->select(sql: 'SELECT id FROM users'));
        $this->assertFalse($db->inTransaction());
    }

    public function testRolledBackTransactionChangesAreDropped(): void
    {
        $db = SqliteDatabase::create();

        $db->beginTransaction();
        $db->execute(sql: 'DELETE FROM users WHERE id = ?', parameters: [1]);
        $this->assertTrue($db->rollBack());

        $this->assertCount(3, $db->select(sql: 'SELECT id FROM users'));
    }

    public function testBeginningASecondTransactionThrows(): void
    {
        $db = SqliteDatabase::create();
        $db->beginTransaction();

        try {
            $db->beginTransaction();
            FrameworkDbTest::fail('Expected LogicException');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('A transaction is already active', $exception->getMessage());
        } finally {
            $db->rollBack();
        }
    }

    public function testCommitWithoutTransactionThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('There was no active transaction');

        SqliteDatabase::create()->commit();
    }

    public function testRollBackWithoutTransactionThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('There was no active transaction');

        SqliteDatabase::create()->rollBack();
    }

    public function testUnclosedTransactionIsReportedWhenTheConnectionIsDestroyed(): void
    {
        $db = SqliteDatabase::create();
        $db->beginTransaction();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('An active transaction was not closed properly');

        unset($db);
    }

    public function testQueriesAreOnlyLoggedOnRequest(): void
    {
        $queryLog = new DbQueryLogList(clock: new SteppingClock());
        $db = SqliteDatabase::create(queryLog: $queryLog);

        $db->select(sql: 'SELECT id FROM users WHERE id = ?', parameters: [1]);
        $db->select(sql: 'SELECT id FROM users WHERE id = ?', parameters: [2], logQuery: true);
        $db->selectRows(sql: 'SELECT id FROM users WHERE id = ?', parameters: [3], logQuery: true);
        $db->execute(sql: 'UPDATE users SET age = ? WHERE id = ?', parameters: [5, 3], logQuery: true);

        $log = $db->getQueryLog();
        $this->assertCount(3, $log);
        $this->assertSame('SELECT id FROM users WHERE id = ?', $log[0]->sqlQuery);
        $this->assertSame([2], $log[0]->params);
        $this->assertSame([5, 3], $log[2]->params);
        $this->assertEqualsWithDelta(1.0, $log[0]->getExecutionTime(), 0.000001);
    }

    public function testFailedQueriesAreNotLogged(): void
    {
        $db = SqliteDatabase::create();

        try {
            $db->select(sql: 'SELECT nothing FROM nowhere', logQuery: true);
        } catch (DbRuntimeException) {
            // expected
        }

        $this->assertSame([], $db->getQueryLog());
    }

    public function testEveryConnectionHasItsOwnQueryLog(): void
    {
        $first = SqliteDatabase::create();
        $second = SqliteDatabase::create();

        $first->select(sql: 'SELECT id FROM users', logQuery: true);

        $this->assertCount(1, $first->getQueryLog());
        $this->assertSame([], $second->getQueryLog());
    }

    public function testCreateInQueryCreatesOnePlaceholderPerValue(): void
    {
        $db = SqliteDatabase::create();

        $this->assertSame('?', $db->createInQuery(values: [5]));
        $this->assertSame('?,?,?', $db->createInQuery(values: [1, 2, 3]));
        $this->assertSame('?,?', $db->createInQuery(values: ['a' => 'x', 'b' => 'y']));
    }

    public function testCreateInQueryRejectsAnEmptyList(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SqliteDatabase::create()->createInQuery(values: []);
    }

    public function testInQueryCanBeUsedToSelect(): void
    {
        $db = SqliteDatabase::create();
        $ids = [1, 3];

        $rows = $db->selectRows(
            sql: 'SELECT name FROM users WHERE id IN (' . $db->createInQuery(values: $ids) . ') ORDER BY id',
            parameters: $ids,
        );

        $this->assertSame(
            ['Anna', 'Cleo'],
            array_map(callback: static fn(DbRow $row): string => $row->getString(column: 'name'), array: $rows),
        );
    }
}
