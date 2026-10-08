<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\db;

use actra\yuf\db\DbQuery;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DbQueryTest extends TestCase
{
    public function testSimpleQueryGetsLimitAndOffsetAsParameters(): void
    {
        $data = DbQuery::createFromSqlQuery(query: 'SELECT id, name FROM user')
            ->getDbQueryData(offset: 20, rowCount: 10);

        $this->assertSame('SELECT id, name FROM user LIMIT ?, ?', $data->query);
        $this->assertSame([20, 10], $data->params);
    }

    public function testWhitespaceAndLineBreaksAreNormalized(): void
    {
        $query = "SELECT  id,\n    name\n  FROM   user\n  WHERE a = ?   AND b=?";

        $data = DbQuery::createFromSqlQuery(query: $query, parameters: [1, 'x'])
            ->getDbQueryData(offset: 0, rowCount: 5);

        $this->assertSame('SELECT id, name FROM user WHERE a = ? AND b=? LIMIT ?, ?', $data->query);
        $this->assertSame([1, 'x', 0, 5], $data->params);
    }

    public function testKeywordsAreCaseInsensitive(): void
    {
        $data = DbQuery::createFromSqlQuery(query: 'select a from u where x = 1')
            ->getDbQueryData(offset: 0, rowCount: 1);

        $this->assertSame('SELECT a FROM u WHERE x = 1 LIMIT ?, ?', $data->query);
    }

    public function testParametersAreAssignedToTheirSections(): void
    {
        $query = 'SELECT COUNT(*) AS c, (SELECT MAX(x) FROM t WHERE y = ?) AS m FROM user u'
            . ' LEFT OUTER JOIN t ON t.id = u.id AND t.k = ? INNER JOIN z ON z.a=u.a WHERE u.id IN (?, ?) OR u.x = 1';

        $data = DbQuery::createFromSqlQuery(query: $query, parameters: [1, 2, 3, 4])
            ->getDbQueryData(offset: 0, rowCount: 10);

        $this->assertSame(
            'SELECT COUNT( * ) AS c,( SELECT MAX( x ) FROM t WHERE y = ? ) AS m FROM user u'
            . ' LEFT OUTER JOIN t ON t.id = u.id AND t.k = ? INNER JOIN z ON z.a=u.a'
            . ' WHERE u.id IN( ?, ? ) OR u.x = 1 LIMIT ?, ?',
            $data->query,
        );
        $this->assertSame([1, 2, 3, 4, 0, 10], $data->params);
    }

    public function testSubQueryInFromKeepsItsParametersBeforeTheWhereParameters(): void
    {
        $data = DbQuery::createFromSqlQuery(
            query: 'SELECT a FROM (SELECT b FROM c WHERE d = ?) x WHERE e = ?',
            parameters: [1, 2],
        )->getDbQueryData(offset: 0, rowCount: 10);

        $this->assertSame('SELECT a FROM( SELECT b FROM c WHERE d = ? ) x WHERE e = ? LIMIT ?, ?', $data->query);
        $this->assertSame([1, 2, 0, 10], $data->params);
    }

    public function testNestedSubQueriesAreKept(): void
    {
        $data = DbQuery::createFromSqlQuery(query: 'SELECT a FROM t WHERE x = (SELECT 1 FROM (SELECT 2 FROM d) q)')
            ->getDbQueryData(offset: 0, rowCount: 10);

        $this->assertSame('SELECT a FROM t WHERE x =( SELECT 1 FROM( SELECT 2 FROM d ) q ) LIMIT ?, ?', $data->query);
    }

    public function testCommaSeparatedTablesStayInTheFromPart(): void
    {
        $data = DbQuery::createFromSqlQuery(query: 'SELECT a FROM u, v WHERE x=1')
            ->getDbQueryData(offset: 0, rowCount: 10);

        $this->assertSame('SELECT a FROM u, v WHERE x=1 LIMIT ?, ?', $data->query);
    }

    #[DataProvider('joinQueryProvider')]
    public function testJoinClausesAreKeptTogether(string $query): void
    {
        $data = DbQuery::createFromSqlQuery(query: $query)->getDbQueryData(offset: 0, rowCount: 10);

        $this->assertSame($query . ' LIMIT ?, ?', $data->query);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function joinQueryProvider(): array
    {
        return [
            'plain join' => ['SELECT a FROM u JOIN v ON v.a = u.a'],
            'straight join and cross join' => ['SELECT a FROM u STRAIGHT_JOIN v ON v.a = u.a CROSS JOIN w'],
            'several modifiers' => ['SELECT a FROM u LEFT OUTER JOIN t ON 1 INNER JOIN z ON 2 NATURAL JOIN y'],
        ];
    }

    #[DataProvider('invalidQueryProvider')]
    public function testInvalidQueriesThrow(string $query, string $message): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains($message);

        DbQuery::createFromSqlQuery(query: $query);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidQueryProvider(): array
    {
        return [
            'empty' => ['', 'The query must not be empty.'],
            'blank' => ["  \n ", 'The query must not be empty.'],
            'from first' => ['FROM x', '"FROM" must be after "SELECT".'],
            'no from' => ['SELECT a', 'The query does not contain any "FROM" part.'],
            'empty from' => ['SELECT a FROM', 'The query does not contain any "FROM" part.'],
            'empty select' => ['SELECT FROM t', 'The query does not contain any "SELECT" part.'],
            'group by' => ['SELECT a FROM t GROUP BY a', '"GROUP" is not supported within the query.'],
            'order by' => ['SELECT a FROM t WHERE x = 1 ORDER BY a', '"ORDER" is not supported within the query.'],
            'limit' => ['SELECT a FROM t LIMIT 1', '"LIMIT" is not supported within the query.'],
            'having' => ['SELECT a FROM t HAVING 1', '"HAVING" is not supported within the query.'],
            'union' => ['SELECT a FROM t UNION SELECT b FROM u', '"UNION" is not supported within the query.'],
            'open sub query' => ['SELECT a FROM t WHERE (x = 1', 'at least one sub query which is not closed'],
            'closing bracket' => ['SELECT a FROM t WHERE x = 1)', ') is not allowed if not part of a sub query.'],
            'bracket first' => ['(SELECT a FROM t)', '( is not allowed before the first SELECT part.'],
            'second from' => ['SELECT a FROM t FROM x', '"FROM" must be after "SELECT".'],
            'where before from' => ['SELECT a WHERE x FROM t', '"WHERE" must be after "FROM".'],
            'second select' => ['SELECT a FROM t SELECT b', '"SELECT" is not allowed if already in'],
            'join without join keyword' => ['SELECT a FROM u LEFT x', 'incomplete join clause at "LEFT"'],
            'outer without join' => ['SELECT a FROM u OUTER x', 'incomplete join clause at "OUTER"'],
            'join followed by modifier' => ['SELECT a FROM u JOIN v LEFT', 'incomplete join clause at "LEFT"'],
        ];
    }

    public function testFromAfterFromIsRejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('"FROM" must be after "SELECT".');

        DbQuery::createFromSqlQuery(query: 'SELECT a FROM t WHERE FROM x');
    }

    public function testMissingParametersThrow(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains(
            'There are not enough parameters for the "?" placeholders within the "WHERE" part.',
        );

        DbQuery::createFromSqlQuery(query: 'SELECT a FROM t WHERE x = ?');
    }

    public function testTooManyParametersThrow(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('There are more parameters than "?" placeholders within the query.');

        DbQuery::createFromSqlQuery(query: 'SELECT a FROM t WHERE x = ?', parameters: [1, 2]);
    }

    public function testMissingParametersOfAnEarlierSectionAreReportedForThatSection(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('not enough parameters for the "?" placeholders within the "WHERE"');

        DbQuery::createFromSqlQuery(query: 'SELECT ? FROM t WHERE x = ?', parameters: [1]);
    }

    public function testAddedPartsAreCombinedInTheirSections(): void
    {
        $dbQuery = DbQuery::createFromSqlQuery(query: 'SELECT a FROM t');
        $dbQuery->addWherePart(wherePart: 'x = ?', parameters: [1]);
        $dbQuery->addWherePart(wherePart: 'y = 2 OR z = ?', parameters: ['a']);
        $dbQuery->addJoinPart(joinPart: 'LEFT   JOIN   k ON k.id = t.id AND k.v = ?', parameters: [7]);
        $dbQuery->addOrderPart(column: 't.name, b.group');
        $dbQuery->addOrderPart(column: 'MATCH(a) AGAINST (? IN BOOLEAN MODE)', parameters: ['q'], ascending: false);

        $data = $dbQuery->getDbQueryData(offset: 0, rowCount: 10);

        $this->assertSame(
            'SELECT a FROM t LEFT JOIN k ON k.id = t.id AND k.v = ? WHERE(x = ?) AND(y = 2 OR z = ?)'
            . ' ORDER BY `t`.`name` ASC, `b`.`group` ASC, MATCH(a) AGAINST(? IN BOOLEAN MODE) DESC LIMIT ?, ?',
            $data->query,
        );
        $this->assertSame([7, 1, 'a', 'q', 0, 10], $data->params);
    }

    public function testAddedWhereConditionIsCombinedWithTheOriginalOne(): void
    {
        $dbQuery = DbQuery::createFromSqlQuery(query: 'SELECT a FROM t WHERE q = ?', parameters: [9]);
        $dbQuery->addWherePart(wherePart: 'x = ?', parameters: [1]);

        $data = $dbQuery->getDbQueryData(offset: 0, rowCount: 10);

        $this->assertSame('SELECT a FROM t WHERE(q = ?) AND(x = ?) LIMIT ?, ?', $data->query);
        $this->assertSame([9, 1, 0, 10], $data->params);
    }

    public function testSingleWhereConditionIsNotWrapped(): void
    {
        $dbQuery = DbQuery::createFromSqlQuery(query: 'SELECT a FROM t');
        $dbQuery->addWherePart(wherePart: "  x  =\n 1 ", parameters: []);

        $this->assertSame(
            'SELECT a FROM t WHERE x = 1 LIMIT ?, ?',
            $dbQuery->getDbQueryData(offset: 0, rowCount: 1)->query,
        );
    }

    public function testAddedJoinPartMayBeLowercase(): void
    {
        $dbQuery = DbQuery::createFromSqlQuery(query: 'SELECT a FROM u');
        $dbQuery->addJoinPart(joinPart: 'left outer join k2 on 1', parameters: []);

        $this->assertSame(
            'SELECT a FROM u left outer join k2 on 1 LIMIT ?, ?',
            $dbQuery->getDbQueryData(offset: 0, rowCount: 1)->query,
        );
    }

    /**
     * @param callable(DbQuery): void $change
     */
    #[DataProvider('invalidPartProvider')]
    public function testInvalidPartsThrow(callable $change, string $message): void
    {
        $dbQuery = DbQuery::createFromSqlQuery(query: 'SELECT a FROM t');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $change($dbQuery);
    }

    /**
     * @return array<string, array{callable(DbQuery): void, string}>
     */
    public static function invalidPartProvider(): array
    {
        return [
            'join without JOIN' => [
                static fn(DbQuery $q) => $q->addJoinPart(joinPart: 'k ON x', parameters: []),
                'The join part must contain the complete JOIN clause',
            ],
            'join keyword inside a word' => [
                static fn(DbQuery $q) => $q->addJoinPart(joinPart: 'xjoin k', parameters: []),
                'The join part must contain the complete JOIN clause',
            ],
            'join with too few parameters' => [
                static fn(DbQuery $q) => $q->addJoinPart(joinPart: 'JOIN k ON a = ?', parameters: []),
                'The amount of parameters (0) does not match the amount of "?" placeholders (1) in "JOIN k ON a = ?".',
            ],
            'blank where' => [
                static fn(DbQuery $q) => $q->addWherePart(wherePart: '  ', parameters: []),
                'The where part must not be empty.',
            ],
            'where with too few parameters' => [
                static fn(DbQuery $q) => $q->addWherePart(wherePart: 'a = ?', parameters: []),
                'The amount of parameters (0) does not match the amount of "?" placeholders (1) in "a = ?".',
            ],
            'where with too many parameters' => [
                static fn(DbQuery $q) => $q->addWherePart(wherePart: 'a = 1', parameters: [1]),
                'The amount of parameters (1) does not match the amount of "?" placeholders (0) in "a = 1".',
            ],
            'empty order column' => [
                static fn(DbQuery $q) => $q->addOrderPart(column: ''),
                'The order column must not be empty.',
            ],
            'empty entry of an order column list' => [
                static fn(DbQuery $q) => $q->addOrderPart(column: 'a, '),
                'The order column must not be empty.',
            ],
            'injection in order column' => [
                static fn(DbQuery $q) => $q->addOrderPart(column: 'a;DROP'),
                'Invalid characters in order column "a;DROP".',
            ],
            'space in order column' => [
                static fn(DbQuery $q) => $q->addOrderPart(column: 'a b'),
                'Invalid characters in order column "a b".',
            ],
            'function in order column without parameters' => [
                static fn(DbQuery $q) => $q->addOrderPart(column: 'f(?)'),
                'Invalid characters in order column "f(?)".',
            ],
            'incomplete order column' => [
                static fn(DbQuery $q) => $q->addOrderPart(column: 'a..b'),
                'Incomplete order column "a..b".',
            ],
            'blank order expression' => [
                static fn(DbQuery $q) => $q->addOrderPart(column: '  ', parameters: ['x']),
                'The order expression must not be empty.',
            ],
            'order expression with direction' => [
                static fn(DbQuery $q) => $q->addOrderPart(column: 'f(?) DESC', parameters: ['x']),
                'The order expression "f(?) DESC" must not contain the sort direction;',
            ],
            'order expression with too many parameters' => [
                static fn(DbQuery $q) => $q->addOrderPart(column: 'f(?)', parameters: ['x', 'y']),
                'The amount of parameters (2) does not match the amount of "?" placeholders (1) in "f(?)".',
            ],
        ];
    }

    public function testOrderColumnsAreEscapedWithBackticks(): void
    {
        $dbQuery = DbQuery::createFromSqlQuery(query: 'SELECT a FROM t');
        $dbQuery->addOrderPart(column: 'x, y.z', ascending: false);

        $this->assertSame(
            'SELECT a FROM t ORDER BY `x` DESC, `y`.`z` DESC LIMIT ?, ?',
            $dbQuery->getDbQueryData(offset: 0, rowCount: 1)->query,
        );
    }

    public function testExistingBackticksOfAnOrderColumnAreNotDoubled(): void
    {
        $dbQuery = DbQuery::createFromSqlQuery(query: 'SELECT a FROM t');
        $dbQuery->addOrderPart(column: '`a`.`b`');

        $this->assertSame(
            'SELECT a FROM t ORDER BY `a`.`b` ASC LIMIT ?, ?',
            $dbQuery->getDbQueryData(offset: 0, rowCount: 1)->query,
        );
    }

    public function testClearOrderPartsRemovesTheSortingAndItsParameters(): void
    {
        $dbQuery = DbQuery::createFromSqlQuery(query: 'SELECT a FROM t');
        $dbQuery->addOrderPart(column: 'a');
        $dbQuery->addOrderPart(column: 'f(?)', parameters: ['x']);
        $dbQuery->clearOrderParts();

        $data = $dbQuery->getDbQueryData(offset: 0, rowCount: 1);

        $this->assertSame('SELECT a FROM t LIMIT ?, ?', $data->query);
        $this->assertSame([0, 1], $data->params);
    }

    public function testGettingTheQueryDataDoesNotChangeTheQuery(): void
    {
        $dbQuery = DbQuery::createFromSqlQuery(query: 'SELECT a FROM t WHERE x = ?', parameters: [1]);

        $first = $dbQuery->getDbQueryData(offset: 0, rowCount: 5);
        $second = $dbQuery->getDbQueryData(offset: 10, rowCount: 5);

        $this->assertSame($first->query, $second->query);
        $this->assertSame([1, 10, 5], $second->params);
    }
}
