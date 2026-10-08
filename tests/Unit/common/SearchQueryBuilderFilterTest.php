<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\common;

use actra\yuf\common\SearchQueryBuilder;
use actra\yuf\db\DbQuery;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

final class SearchQueryBuilderFilterTest extends TestCase
{
    private const string LIKE = " LIKE ? ESCAPE '!'";

    /**
     * @return array<string, array{string, string, list<string>}>
     */
    public static function filterProvider(): array
    {
        $like = 'c' . SearchQueryBuilderFilterTest::LIKE;
        $notLike = '((c NOT' . SearchQueryBuilderFilterTest::LIKE . ') OR c IS NULL)';

        return [
            'empty' => ['  ', '1=1', []],
            'not empty' => ['.', "(c!='' AND c IS NOT NULL)", []],
            'empty or null' => ['_', "((c='') OR (c IS NULL))", []],
            'equal' => ['"bar baz"', 'c=?', ['bar baz']],
            'contains phrase' => ['*foo bar*', $like, ['%foo bar%']],
            'contains phrase with percent' => ['*50%*', $like, ['%50!%%']],
            'percent is literal' => ['%foo%', '(' . $like . ')', ['%!%foo!%%']],
            'underscore and escape character are literal' => [
                'a_b wow!',
                '(' . $like . ' OR ' . $like . ')',
                ['%a!_b%', '%wow!!%'],
            ],
            'question mark is bound' => ['?x', '(' . $like . ')', ['%?x%']],
            'star is a wildcard' => ['a*b', '(' . $like . ')', ['%a%b%']],
            'words separated by space or comma' => [
                'foo bar,baz',
                '(' . $like . ' OR ' . $like . ' OR ' . $like . ')',
                ['%foo%', '%bar%', '%baz%'],
            ],
            'double-quoted phrase is equal' => ['foo "bar baz"', 'c=? AND (' . $like . ')', ['bar baz', '%foo%']],
            'single-quoted phrase is contained' => [
                "foo 'bar baz'",
                '(' . $like . ' OR ' . $like . ')',
                ['%foo%', '%bar baz%'],
            ],
            'apostrophes are no quotes' => [
                "o'neil o'brien",
                '(' . $like . ' OR ' . $like . ')',
                ["%o'neil%", "%o'brien%"],
            ],
            'operators' => [
                '-foo +bar baz',
                $notLike . ' AND ' . $like . ' AND (' . $like . ')',
                ['%foo%', '%bar%', '%baz%'],
            ],
            'exclamation mark' => ['!foo', $notLike, ['%foo%']],
            'operator before phrase' => [
                '-"foo bar" +\'baz qux\'',
                $notLike . ' AND ' . $like,
                ['%foo bar%', '%baz qux%'],
            ],
            'lone operator is ignored' => ['- x', '(' . $like . ')', ['%x%']],
        ];
    }

    /**
     * @param list<string> $expectedParameters
     */
    #[DataProvider('filterProvider')]
    public function testCreateSQLFilters(string $value, string $expectedQuery, array $expectedParameters): void
    {
        $data = SearchQueryBuilder::createSqlFilters(filterArr: [' c ' => $value]);

        $this->assertSame($expectedQuery, $data->query);
        $this->assertSame($expectedParameters, $data->params);
    }

    public function testCreateSQLFiltersCombinesColumnsAndAcceptsExpressions(): void
    {
        $data = SearchQueryBuilder::createSqlFilters(filterArr: [
            "CONCAT_WS(' ', a.firstName, a.lastName)" => 'haas',
            'b.city' => '',
            'b.zip' => 80,
        ]);

        $this->assertSame(
            "(CONCAT_WS(' ', a.firstName, a.lastName)" . SearchQueryBuilderFilterTest::LIKE . ') AND (b.zip'
            . SearchQueryBuilderFilterTest::LIKE . ')',
            $data->query,
        );
        $this->assertSame(['%haas%', '%80%'], $data->params);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidColumnProvider(): array
    {
        return [
            'empty' => [' '],
            'placeholder' => ['IF(a = ?, b, c)'],
        ];
    }

    #[DataProvider('invalidColumnProvider')]
    public function testCreateSQLFiltersRejectsInvalidColumn(string $column): void
    {
        $this->expectException(InvalidArgumentException::class);

        SearchQueryBuilder::createSqlFilters(filterArr: [$column => 'haas']);
    }

    /**
     * @return array<string, array{string, list<int>}>
     */
    public static function databaseFilterProvider(): array
    {
        return [
            'question mark' => ['?haas', [1]],
            'percent is literal' => ['50%', [3]],
            'underscore is literal' => ['a_b', [4]],
            'star is a wildcard' => ['5*0', [3, 5]],
            'expression column' => ['haas zürich', [1, 2]],
            'operators' => ['haas -kapelle', [2]],
            'equal' => ['"Haas zürich"', [2]],
        ];
    }

    /**
     * @param list<int> $expectedIds
     */
    #[DataProvider('databaseFilterProvider')]
    #[RequiresPhpExtension('pdo_sqlite')]
    public function testCreateSQLFiltersAgainstDatabase(string $value, array $expectedIds): void
    {
        $pdo = new PDO(dsn: 'sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec(statement: 'CREATE TABLE item (id INTEGER PRIMARY KEY, name TEXT NOT NULL, city TEXT NOT NULL)');
        $pdo->exec(
            statement: "INSERT INTO item (id, name, city) VALUES (1, '?Haas Kapelle', 'Bern'), (2, 'Haas', 'zürich'),"
            . " (3, '50% Rabatt', ''), (4, 'a_b', ''), (5, '500', ''), (6, 'axb', '')",
        );
        $dbQuery = DbQuery::createFromSqlQuery(query: 'SELECT id FROM item');
        $dbQuery->addOrderPart(column: 'id');
        $data = SearchQueryBuilder::createSqlFilters(filterArr: ["name || ' ' || city" => $value]);
        $dbQuery->addWherePart(wherePart: $data->query, parameters: $data->params);
        $queryData = $dbQuery->getDbQueryData(offset: 0, rowCount: 100);

        $statement = $pdo->prepare(query: $queryData->query);
        $this->assertInstanceOf(PDOStatement::class, $statement);
        $statement->execute(params: $queryData->params);

        $this->assertSame($expectedIds, $statement->fetchAll(mode: PDO::FETCH_COLUMN));
    }

    public function testCreateSQLSearchQuotesColumnsAndEscapesWords(): void
    {
        $result = SearchQueryBuilder::createSqlSearch(
            string: 'foo "bar baz" 50%',
            columns: ['name', 't.city', '`order`'],
        );
        $word = '(`name`' . SearchQueryBuilderFilterTest::LIKE . ' OR `t`.`city`' . SearchQueryBuilderFilterTest::LIKE
            . ' OR `order`' . SearchQueryBuilderFilterTest::LIKE . ')';

        $this->assertSame(
            [
                'sql' => '(' . $word . ' AND ' . $word . ' AND ' . $word . ')',
                'params' => [
                    '%foo%',
                    '%foo%',
                    '%foo%',
                    '%bar baz%',
                    '%bar baz%',
                    '%bar baz%',
                    '%50!%%',
                    '%50!%%',
                    '%50!%%',
                ],
                'searchWords' => ['foo', 'bar baz', '50%'],
            ],
            $result,
        );
    }

    public function testCreateSQLSearchWithoutWords(): void
    {
        $this->assertSame(
            ['sql' => '', 'params' => [], 'searchWords' => []],
            SearchQueryBuilder::createSqlSearch(string: ' , ', columns: ['name']),
        );
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public static function invalidSearchColumnsProvider(): array
    {
        return [
            'none' => [[]],
            'expression' => [['CONCAT(a, b)']],
            'placeholder' => [['a?']],
            'backtick injection' => [['`a` OR 1=1']],
            'trailing line break' => [["name\n"]],
        ];
    }

    /**
     * @param list<string> $columns
     */
    #[DataProvider('invalidSearchColumnsProvider')]
    public function testCreateSQLSearchRejectsInvalidColumns(array $columns): void
    {
        $this->expectException(InvalidArgumentException::class);

        SearchQueryBuilder::createSqlSearch(string: 'foo', columns: $columns);
    }
}
