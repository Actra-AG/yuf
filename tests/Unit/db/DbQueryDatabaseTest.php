<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\db;

use actra\yuf\db\DbQuery;
use actra\yuf\tests\Double\db\SqliteDatabase;
use PHPUnit\Framework\TestCase;

/**
 * `DbQuery` together with a real (SQLite) database: generated SQL is run, not only compared.
 */
final class DbQueryDatabaseTest extends TestCase
{
    public function testSelectFromDbReturnsTheRequestedPageInTheRequestedOrder(): void
    {
        $dbQuery = DbQuery::createFromSqlQuery(query: 'SELECT id, name FROM users WHERE id > ?', parameters: [0]);
        $dbQuery->addOrderPart(column: 'name', ascending: false);

        $rows = $dbQuery->selectFromDb(db: SqliteDatabase::create(), offset: 1, rowCount: 2);

        $this->assertSame(['Ben', 'Anna'], array_column(array: $rows, column_key: 'name'));
    }

    public function testAddedPartsAreAppliedWithBoundValues(): void
    {
        $dbQuery = DbQuery::createFromSqlQuery(query: 'SELECT u.name FROM users u');
        $dbQuery->addJoinPart(joinPart: 'LEFT JOIN users o ON o.id = u.id AND o.age > ?', parameters: [35]);
        $dbQuery->addWherePart(wherePart: 'o.id IS NOT NULL OR u.name = ?', parameters: ["Anna' --"]);
        $dbQuery->addOrderPart(column: 'u.id');

        $rows = $dbQuery->selectFromDb(db: SqliteDatabase::create(), offset: 0, rowCount: 10);

        $this->assertSame(['Cleo'], array_column(array: $rows, column_key: 'name'));
    }

    public function testOrderExpressionWithParameterIsExecuted(): void
    {
        $dbQuery = DbQuery::createFromSqlQuery(query: 'SELECT name FROM users');
        $dbQuery->addOrderPart(column: 'CASE WHEN name = ? THEN 0 ELSE 1 END', parameters: ['Cleo']);
        $dbQuery->addOrderPart(column: 'name');

        $rows = $dbQuery->selectFromDb(db: SqliteDatabase::create(), offset: 0, rowCount: 3);

        $this->assertSame(['Cleo', 'Anna', 'Ben'], array_column(array: $rows, column_key: 'name'));
    }

    public function testTotalAmountIgnoresPagingSortingAndSelectedColumns(): void
    {
        $dbQuery = DbQuery::createFromSqlQuery(
            query: 'SELECT (SELECT COUNT(*) FROM users x WHERE x.id < u.id AND x.name <> ?) AS earlier FROM users u'
            . ' WHERE u.id > ?',
            parameters: ['nobody', 1],
        );
        $dbQuery->addOrderPart(column: 'earlier', ascending: false);

        $this->assertSame(2, $dbQuery->getTotalAmount(db: SqliteDatabase::create()));
    }

    public function testTotalAmountOfAnEmptyResultIsZero(): void
    {
        $dbQuery = DbQuery::createFromSqlQuery(query: 'SELECT id FROM users WHERE id > ?', parameters: [100]);

        $this->assertSame(0, $dbQuery->getTotalAmount(db: SqliteDatabase::create()));
    }
}
