<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\common;

use actra\yuf\common\SearchQueryBuilder;
use actra\yuf\db\DbQuery;
use actra\yuf\db\DbQueryData;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

final class SearchQueryBuilderBooleanQueryTest extends TestCase
{
    private const string LIKE = " LIKE ? ESCAPE '!'";

    /**
     * @param list<string> $expectedParameters
     */
    private function assertBooleanQuery(
        string $expectedQuery,
        array $expectedParameters,
        string $queryText,
        string $fieldNames = 'name',
    ): void {
        $data = SearchQueryBuilder::createBooleanQuery(spaceSeparatedFieldNames: $fieldNames, queryText: $queryText);

        $this->assertSame($expectedQuery, $data->query);
        $this->assertSame($expectedParameters, $data->params);
    }

    public function testSingleWordIsLowercasedAndWrappedInWildcards(): void
    {
        $this->assertBooleanQuery('((name' . SearchQueryBuilderBooleanQueryTest::LIKE . '))', ['%term%'], 'Term');
    }

    public function testEveryFieldGetsItsOwnCondition(): void
    {
        $this->assertBooleanQuery(
            '((a.name' . SearchQueryBuilderBooleanQueryTest::LIKE . ' OR b.city'
                . SearchQueryBuilderBooleanQueryTest::LIKE . '))',
            ['%term%', '%term%'],
            'term',
            'a.name b.city',
        );
    }

    public function testSeveralWordsAreCombinedWithOr(): void
    {
        $this->assertBooleanQuery(
            '((name' . SearchQueryBuilderBooleanQueryTest::LIKE . ') OR (name'
                . SearchQueryBuilderBooleanQueryTest::LIKE . '))',
            ['%term%', '%kap%'],
            'term  kap',
        );
    }

    /**
     * @return array<string, array{string, string, list<string>}>
     */
    public static function operatorProvider(): array
    {
        $word = '(name' . SearchQueryBuilderBooleanQueryTest::LIKE . ')';

        return [
            'and' => ['term and kap', '(' . $word . ' AND ' . $word . ')', ['%term%', '%kap%']],
            'or' => ['term OR kap', '(' . $word . ' OR ' . $word . ')', ['%term%', '%kap%']],
            'not' => ['term not kap', '(' . $word . ' AND (NOT ' . $word . '))', ['%term%', '%kap%']],
            'shorthands' => [
                'term +kap -bar',
                '(' . $word . ' AND ' . $word . ' AND (NOT ' . $word . '))',
                ['%term%', '%kap%', '%bar%'],
            ],
            'shorthand before a phrase' => [
                'term -"a b"',
                '(' . $word . ' AND (NOT ' . $word . '))',
                ['%term%', '%a b%'],
            ],
            'operator word after an operator' => [
                'term and or',
                '(' . $word . ' AND ' . $word . ')',
                ['%term%', '%or%'],
            ],
            'leading operator is a word' => ['not term', '(' . $word . ' OR ' . $word . ')', ['%not%', '%term%']],
            'leading shorthand is a word' => ['+term', '(' . $word . ')', ['%+term%']],
            'trailing operator is ignored' => ['term and', '(' . $word . ')', ['%term%']],
            'lone shorthand applies to the next word' => [
                'term + kap',
                '(' . $word . ' AND ' . $word . ')',
                ['%term%', '%kap%'],
            ],
            'shorthand within a word is literal' => ['term-kap', '(' . $word . ')', ['%term-kap%']],
        ];
    }

    /**
     * @param list<string> $expectedParameters
     */
    #[DataProvider('operatorProvider')]
    public function testOperators(string $queryText, string $expectedQuery, array $expectedParameters): void
    {
        $this->assertBooleanQuery($expectedQuery, $expectedParameters, $queryText);
    }

    public function testQuotedPhraseIsOneWordAndQuotedOperatorsAreLiteral(): void
    {
        $word = '(name' . SearchQueryBuilderBooleanQueryTest::LIKE . ')';
        $this->assertBooleanQuery(
            '(' . $word . ' OR ' . $word . ' OR ' . $word . ' OR ' . $word . ')',
            ['%foo%', '%term kap%', '%and%', '%a -b%'],
            'foo "Term Kap" "and" "a -b"',
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function specialCharacterProvider(): array
    {
        return [
            'question mark' => ['?term', '%?term%'],
            'percent' => ['50%', '%50!%%'],
            'underscore' => ['a_b', '%a!_b%'],
            'escape character' => ['wow!', '%wow!!%'],
            'single quote' => ["o'neil", "%o'neil%"],
            'backslash' => ['a\\b', '%a\\b%'],
        ];
    }

    #[DataProvider('specialCharacterProvider')]
    public function testSpecialCharactersAreBoundLiterally(string $queryText, string $expectedParameter): void
    {
        $this->assertBooleanQuery(
            '((name' . SearchQueryBuilderBooleanQueryTest::LIKE . '))',
            [$expectedParameter],
            $queryText,
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function emptyQueryProvider(): array
    {
        return [
            'empty' => [''],
            'spaces' => ['   '],
            'empty quotes' => ['""'],
            'tags only' => ['<b></b>'],
        ];
    }

    #[DataProvider('emptyQueryProvider')]
    public function testEmptyQueryMatchesEverything(string $queryText): void
    {
        $this->assertBooleanQuery('1=1', [], $queryText);
    }

    public function testTagsAreStripped(): void
    {
        $this->assertBooleanQuery(
            '((name' . SearchQueryBuilderBooleanQueryTest::LIKE . '))',
            ['%term%'],
            ' <b>Term</b> ',
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidFieldNameProvider(): array
    {
        return [
            'empty' => [' '],
            'injection' => ['name;DROP'],
            'placeholder' => ['a?b'],
            'digits only' => ['123'],
            'too many parts' => ['a.b.c.d'],
            'quote' => ["name'"],
            'expression' => ['CONCAT(a,b)'],
            'placeholder in backticks' => ['`a?b`'],
        ];
    }

    #[DataProvider('invalidFieldNameProvider')]
    public function testInvalidFieldNameThrows(string $fieldNames): void
    {
        $this->expectException(InvalidArgumentException::class);

        $_ = SearchQueryBuilder::createBooleanQuery(spaceSeparatedFieldNames: $fieldNames, queryText: 'term');
    }

    public function testValidFieldNames(): void
    {
        $data = SearchQueryBuilder::createBooleanQuery(
            spaceSeparatedFieldNames: '2011_module.titel `order` db.t.c $col',
            queryText: 'term',
        );

        $this->assertSame(
            "((2011_module.titel LIKE ? ESCAPE '!' OR `order` LIKE ? ESCAPE '!' OR db.t.c LIKE ? ESCAPE '!'"
            . " OR \$col LIKE ? ESCAPE '!'))",
            $data->query,
        );
    }

    public function testDbQueryAcceptsQueryWithQuestionMark(): void
    {
        $data = SearchQueryBuilder::createBooleanQuery(spaceSeparatedFieldNames: 'name', queryText: '?term kap');
        $dbQuery = DbQuery::createFromSqlQuery(query: 'SELECT id FROM item');

        $dbQuery->addWherePart(wherePart: $data->query, parameters: $data->params);

        $this->assertEquals(
            new DbQueryData(
                query: "SELECT id FROM item WHERE((name LIKE ? ESCAPE '!') OR(name LIKE ? ESCAPE '!')) LIMIT ?, ?",
                params: ['%?term%', '%kap%', 0, 10],
            ),
            $dbQuery->getDbQueryData(offset: 0, rowCount: 10),
        );
    }

    /**
     * @return array<string, array{string, list<int>}>
     */
    public static function databaseSearchProvider(): array
    {
        return [
            'question mark' => ['?term', [1]],
            'percent is literal' => ['50%', [3]],
            'underscore is literal' => ['a_b', [4]],
            'escape character is literal' => ['wow!', [6]],
            'backslash is literal' => ['c:\\temp', [7]],
            'case-insensitive or' => ['TERM 500', [1, 2, 5]],
            'and not' => ['term -kapelle', [2]],
            'phrase' => ['"term kapelle"', [1]],
        ];
    }

    /**
     * Uses an in-memory SQLite database, which needs the ESCAPE clause just like MySQL with NO_BACKSLASH_ESCAPES.
     *
     * @param list<int> $expectedIds
     */
    #[DataProvider('databaseSearchProvider')]
    #[RequiresPhpExtension('pdo_sqlite')]
    public function testSearchAgainstDatabase(string $queryText, array $expectedIds): void
    {
        $pdo = new PDO(dsn: 'sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec(statement: 'CREATE TABLE item (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $pdo->exec(
            statement: "INSERT INTO item (id, name) VALUES (1, '?Term Kapelle'), (2, 'Term'), (3, '50% Rabatt'),"
            . " (4, 'a_b'), (5, '500'), (6, 'wow!'), (7, 'C:\\Temp'), (8, 'axb'), (9, 'c:temp')",
        );
        $dbQuery = DbQuery::createFromSqlQuery(query: 'SELECT id FROM item');
        $dbQuery->addOrderPart(column: 'id');
        $data = SearchQueryBuilder::createBooleanQuery(spaceSeparatedFieldNames: 'name', queryText: $queryText);
        $dbQuery->addWherePart(wherePart: $data->query, parameters: $data->params);
        $queryData = $dbQuery->getDbQueryData(offset: 0, rowCount: 100);

        $statement = $pdo->prepare(query: $queryData->query);
        $this->assertInstanceOf(PDOStatement::class, $statement);
        $statement->execute(params: $queryData->params);

        $this->assertSame($expectedIds, $statement->fetchAll(mode: PDO::FETCH_COLUMN));
    }

    public function testBooleanQueryUsesTheFirstWordsOnly(): void
    {
        $words = implode(separator: ' ', array: array_map(static fn(int $i): string => 'w' . $i, range(1, 30)));

        $data = SearchQueryBuilder::createBooleanQuery(spaceSeparatedFieldNames: 'a b', queryText: $words);

        $this->assertCount(SearchQueryBuilder::MAX_SEARCH_WORDS * 2, $data->params);
    }
}
