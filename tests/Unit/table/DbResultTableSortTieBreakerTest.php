<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\table;

use actra\yuf\db\DbQuery;
use actra\yuf\db\FrameworkDb;
use actra\yuf\html\HtmlText;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\table\column\DefaultColumn;
use actra\yuf\table\table\DbResultTable;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\template\TemplateEngineFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * `DbResultTable::addSortTieBreaker()` appends a stable secondary order after the sort column.
 */
final class DbResultTableSortTieBreakerTest extends TestCase
{
    /**
     * @param array<string, string> $query
     */
    private function createTable(DbQuery $dbQuery, array $query = []): DbResultTable
    {
        $table = new DbResultTable(
            identifier: 'customers',
            db: DbResultTableSortTieBreakerTest::createStub(FrameworkDb::class),
            dbQuery: $dbQuery,
            templateEngine: TemplateEngineFactory::create(
                cacheDirectory: sys_get_temp_dir() . '/yuf-db-result-table-tie-breaker-test/',
                templateBaseDirectory: sys_get_temp_dir() . '/',
            ),
            httpRequest: HttpRequestFactory::create(queryParameters: $query),
            session: new Session(storage: new ArraySessionStorage()),
        );
        foreach (['email', 'name'] as $identifier) {
            $table->addColumn(
                abstractTableColumn: new DefaultColumn(
                    identifier: $identifier,
                    label: HtmlText::fromText(text: $identifier),
                    isSortable: true,
                ),
                isDefaultSortColumn: $identifier === 'name',
            );
        }

        return $table;
    }

    private static function getOrderBy(DbResultTable $table): string
    {
        $table->fillBySelectQuery();
        $query = $table->dbQuery->getDbQueryData(offset: 0, rowCount: 1)->query;

        return substr(string: $query, offset: (int) strpos(haystack: $query, needle: 'ORDER BY'));
    }

    public function testFollowsTheSortColumnOfTheUser(): void
    {
        $table = $this->createTable(
            dbQuery: DbQuery::createFromSqlQuery(query: 'SELECT id, email, name FROM customer'),
            query: ['sort' => 'customers|email|ASC'],
        );
        $table->addSortTieBreaker(column: 'id');

        $this->assertStringStartsWith('ORDER BY `email` ASC, `id` ASC', DbResultTableSortTieBreakerTest::getOrderBy(
            table: $table,
        ));
    }

    public function testDefaultSortingKeepsTheOrderPartsOfTheQuery(): void
    {
        $dbQuery = DbQuery::createFromSqlQuery(query: 'SELECT id, email, name FROM customer');
        $dbQuery->addOrderPart(column: 'c.priority', ascending: false);
        $table = $this->createTable(dbQuery: $dbQuery);
        $table->addSortTieBreaker(column: 'c.id', ascending: false);

        $this->assertStringStartsWith(
            'ORDER BY `c`.`priority` DESC, `name` ASC, `c`.`id` DESC',
            DbResultTableSortTieBreakerTest::getOrderBy(table: $table),
        );
    }

    public function testSortingOfTheUserReplacesTheOrderPartsOfTheQueryButKeepsTheTieBreaker(): void
    {
        $dbQuery = DbQuery::createFromSqlQuery(query: 'SELECT id, email, name FROM customer');
        $dbQuery->addOrderPart(column: 'c.priority', ascending: false);
        $table = $this->createTable(dbQuery: $dbQuery, query: ['sort' => 'customers|email|DESC']);
        $table->addSortTieBreaker(column: 'id');

        $this->assertStringStartsWith('ORDER BY `email` DESC, `id` ASC', DbResultTableSortTieBreakerTest::getOrderBy(
            table: $table,
        ));
    }

    public function testTieBreakerEqualToTheSortColumnIsSkipped(): void
    {
        $table = $this->createTable(
            dbQuery: DbQuery::createFromSqlQuery(query: 'SELECT id, email, name FROM customer'),
            query: ['sort' => 'customers|email|DESC'],
        );
        $table->addSortTieBreaker(column: '`email`');
        $table->addSortTieBreaker(column: 'id');

        $this->assertStringStartsWith('ORDER BY `email` DESC, `id` ASC LIMIT', DbResultTableSortTieBreakerTest::getOrderBy(
            table: $table,
        ));
    }

    public function testInvalidCharacterIsRejected(): void
    {
        $table = $this->createTable(dbQuery: DbQuery::createFromSqlQuery(query: 'SELECT id FROM customer'));

        $this->expectException(InvalidArgumentException::class);

        $table->addSortTieBreaker(column: 'id; DROP TABLE customer');
    }
}
