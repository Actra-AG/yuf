<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\table;

use actra\yuf\core\HttpRequest;
use actra\yuf\core\RequestMethodEnum;
use actra\yuf\db\DbQuery;
use actra\yuf\db\DbQueryData;
use actra\yuf\db\FrameworkDb;
use actra\yuf\html\HtmlText;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\table\column\DefaultColumn;
use actra\yuf\table\filter\DateFilterField;
use actra\yuf\table\filter\FilterOption;
use actra\yuf\table\filter\OptionsFilterField;
use actra\yuf\table\filter\TableFilter;
use actra\yuf\table\filter\TextFilterField;
use actra\yuf\table\renderer\SortableTableHeadRenderer;
use actra\yuf\table\table\DbResultTable;
use actra\yuf\table\TableHelper;
use actra\yuf\template\TemplateEngine;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\db\SqliteDatabase;
use actra\yuf\tests\Double\security\InMemoryCsrfTokenSource;
use actra\yuf\tests\Double\template\TemplateEngineFactory;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * Rendered HTML and executed queries of a `DbResultTable` on a SQLite database (users: Anna 30, Ben without age, Cleo
 * 41).
 */
final class DbResultTableRenderTest extends TestCase
{
    private TemplateEngine $templateEngine;
    private Session $session;

    #[Override]
    protected function setUp(): void
    {
        // The template cache checks for its files via is_dir()/file_exists(), which would report stale results
        clearstatcache();
        $this->templateEngine = TemplateEngineFactory::create(
            cacheDirectory: sys_get_temp_dir() . '/yuf-db-result-table-render-test/',
            templateBaseDirectory: dirname(path: __DIR__, levels: 3) . '/',
        );
        $this->session = new Session(storage: new ArraySessionStorage());
    }

    /**
     * @param array<string, string> $query
     */
    private function createTable(
        array $query = [],
        int $itemsPerPage = 25,
        string $sql = 'SELECT id, name, age FROM users',
        ?TableFilter $tableFilter = null,
        ?HttpRequest $httpRequest = null,
        ?SortableTableHeadRenderer $sortableTableHeadRenderer = null,
        ?DbQuery $dbQuery = null,
        ?FrameworkDb $db = null,
    ): DbResultTable {
        $table = new DbResultTable(
            identifier: 'users',
            db: $db ?? SqliteDatabase::create(),
            dbQuery: $dbQuery ?? DbQuery::createFromSqlQuery(query: $sql),
            templateEngine: $this->templateEngine,
            httpRequest: $httpRequest ?? HttpRequestFactory::create(queryParameters: $query),
            session: $this->session,
            tableFilter: $tableFilter,
            sortableTableHeadRenderer: $sortableTableHeadRenderer,
            itemsPerPage: $itemsPerPage,
        );
        $table->addColumn(
            abstractTableColumn: TableHelper::createDefaultColumn(identifier: 'id', label: 'Id', isSortable: true),
            isDefaultSortColumn: true,
        );
        $table->addColumn(
            abstractTableColumn: TableHelper::createDefaultColumn(identifier: 'name', label: 'Name', isSortable: true),
        );
        $table->addColumn(abstractTableColumn: TableHelper::createDefaultColumn(identifier: 'age', label: 'Age'));

        return $table;
    }

    /**
     * The names of the rows in the order of the HTML.
     *
     * @return list<string>
     */
    private static function names(string $html): array
    {
        preg_match_all(pattern: '#<tr><td>\d+</td>\n<td>(\w+)</td>#', subject: $html, matches: $matches);

        return $matches[1];
    }

    private function createFilter(HttpRequest $httpRequest): TableFilter
    {
        $filter = new TableFilter(
            identifier: 'f',
            httpRequest: $httpRequest,
            session: $this->session,
            csrfTokenSource: new InMemoryCsrfTokenSource(),
        );
        $filter->addPrimaryField(
            abstractTableFilterField: new TextFilterField(
                parentFilter: $filter,
                filterFieldIdentifier: 'name',
                label: HtmlText::fromHtml(html: 'Name'),
                dataTableColumnReference: 'name',
            ),
        );
        $filter->addPrimaryField(
            abstractTableFilterField: new OptionsFilterField(
                parentFilter: $filter,
                filterFieldIdentifier: 'age',
                label: HtmlText::fromHtml(html: 'Age'),
                filterOptions: [
                    new FilterOption(
                        identifier: 'all',
                        label: 'All',
                        whereCondition: new DbQueryData(query: '1=1', params: []),
                    ),
                    new FilterOption(
                        identifier: 'old',
                        label: 'Old <b>',
                        whereCondition: new DbQueryData(query: 'age>?', params: [35]),
                    ),
                ],
            ),
        );
        $filter->addSecondaryField(
            abstractTableFilterField: new DateFilterField(
                parentFilter: $filter,
                filterFieldIdentifier: 'since',
                label: HtmlText::fromHtml(html: 'Since'),
                dataTableColumnReference: 'created',
                dateMustBeSameOrLater: true,
            ),
        );

        return $filter;
    }

    public function testExactMarkupWithFilterPaginationAndSortLinks(): void
    {
        $httpRequest = HttpRequestFactory::create(queryParameters: ['sort' => 'users|name|DESC']);
        $table = $this->createTable(
            itemsPerPage: 2,
            tableFilter: $this->createFilter(httpRequest: $httpRequest),
            httpRequest: $httpRequest,
        );
        $table->addAdditionalLinkParameter(key: 'lang', value: 'de ch');

        $this->assertSame(
            rtrim(
                string: (string) file_get_contents(
                    filename: dirname(path: __DIR__, levels: 2) . '/Fixture/table/db-result-table.html',
                ),
            ),
            $table->render(),
        );
    }

    public function testRowsAreSortedByTheDefaultSortColumnAscending(): void
    {
        $this->assertSame(
            ['Anna', 'Ben', 'Cleo'],
            DbResultTableRenderTest::names(html: $this->createTable()->render()),
        );
    }

    public function testRowsAreSortedByTheChosenColumn(): void
    {
        $html = $this->createTable(query: ['sort' => 'users|name|DESC'])->render();

        $this->assertSame(['Cleo', 'Ben', 'Anna'], DbResultTableRenderTest::names(html: $html));
    }

    public function testChosenSortingReplacesTheOrderOfTheQuery(): void
    {
        $this->assertSame(
            ['Cleo', 'Ben', 'Anna'],
            DbResultTableRenderTest::names(html: $this->createTable(dbQuery: $this->orderedQuery())->render()),
            'default sorting is added to the order of the query, which comes first',
        );
        $this->assertSame(
            ['Anna', 'Ben', 'Cleo'],
            DbResultTableRenderTest::names(
                html: $this->createTable(query: ['sort' => 'users|id|ASC'], dbQuery: $this->orderedQuery())->render(),
            ),
        );
    }

    private function orderedQuery(): DbQuery
    {
        $dbQuery = DbQuery::createFromSqlQuery(query: 'SELECT id, name, age FROM users');
        $dbQuery->addOrderPart(column: 'name', ascending: false);

        return $dbQuery;
    }

    public function testUnknownSortColumnOrDirectionIsIgnored(): void
    {
        $this->assertSame(
            ['Anna', 'Ben', 'Cleo'],
            DbResultTableRenderTest::names(html: $this->createTable(query: ['sort' => 'users|age|DESC'])->render()),
        );
        $this->assertSame(
            ['Anna', 'Ben', 'Cleo'],
            DbResultTableRenderTest::names(
                html: $this->createTable(query: ['sort' => 'users|name; DROP TABLE users|ASC'])->render(),
            ),
        );
        $this->assertSame(
            ['Anna', 'Ben', 'Cleo'],
            DbResultTableRenderTest::names(
                html: $this->createTable(query: ['sort' => 'users|name|SIDEWAYS'])->render(),
            ),
        );
    }

    public function testSecondPageShowsTheRestAndTheTotalAmountComesFromTheDatabase(): void
    {
        $table = $this->createTable(query: ['page' => '2|users'], itemsPerPage: 2);

        $html = $table->render();

        $this->assertSame(['Cleo'], DbResultTableRenderTest::names(html: $html));
        $this->assertSame(3, $table->getTotalAmount());
        $this->assertSame(2, $table->getCurrentPaginationPage());
        $this->assertStringContainsString('Es wurden <strong>3</strong> Resultate gefunden.', $html);
        $this->assertStringContainsString('<li class="back">', $html);
        $this->assertStringContainsString('<li class="nextdisabled">', $html);
    }

    public function testOnePageShowsNoPaginationAndNoFooter(): void
    {
        $html = $this->createTable()->render();

        $this->assertStringNotContainsString('pagination', $html);
        $this->assertStringNotContainsString('table-meta-footer', $html);
        $this->assertStringStartsWith(
            '<div class="table-meta table-meta-header"><p class="search-result">'
            . 'Es wurden <strong>3</strong> Resultate gefunden.</p></div><div class="table-wrap"><table class="table">',
            $html,
        );
    }

    public function testLimitToOnePageSkipsTheCountQuery(): void
    {
        $table = $this->createTable(itemsPerPage: 2);
        $table->limitToOnePage = true;

        $this->assertSame(2, $table->getTotalAmount());
    }

    public function testEmptyResultShowsTheNoDataTextAndTheFilter(): void
    {
        $httpRequest = HttpRequestFactory::create();
        $table = $this->createTable(
            sql: 'SELECT id, name, age FROM users WHERE id > 99',
            tableFilter: $this->createFilter(httpRequest: $httpRequest),
            httpRequest: $httpRequest,
        );

        $html = $table->render();

        $this->assertStringStartsWith('<div class="table-filter-wrapper">', $html);
        $this->assertStringEndsWith('</div><p class="no-entry">Es wurden keine Einträge gefunden.</p>', $html);
        $this->assertSame(0, $table->getTotalAmount());
    }

    public function testEmptyResultWithoutFilter(): void
    {
        $this->assertSame(
            '<p class="no-entry">Es wurden keine Einträge gefunden.</p>',
            $this->createTable(sql: 'SELECT id, name, age FROM users WHERE id > 99')->render(),
        );
    }

    public function testSortLinks(): void
    {
        $html = $this->createTable()->render();

        $this->assertStringContainsString(
            '<th scope="col" class="sort sort sort-asc"><a href="?sort=users|id|DESC">Id</a></th>' . "\n"
            . '<th scope="col" class="sort sort"><a href="?sort=users|name|ASC">Name</a></th>' . "\n"
            . '<th scope="col">Age</th>',
            $html,
        );
        $html = $this->createTable(query: ['sort' => 'users|id|DESC'])->render();
        $this->assertStringContainsString(
            '<th scope="col" class="sort sort sort-desc"><a href="?sort=users|id|ASC">',
            $html,
        );
    }

    public function testSortLinkOfAColumnThatSortsDescendingByDefault(): void
    {
        $table = $this->createTable(sql: 'SELECT id, name, age, 1 AS extra FROM users');
        $table->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'extra',
                label: 'Extra',
                isSortable: true,
                sortAscendingByDefault: false,
            ),
        );

        $this->assertStringContainsString('<a href="?sort=users|extra|DESC">Extra</a>', $table->render());
    }

    public function testSortableHeadRendererOptions(): void
    {
        $renderer = new SortableTableHeadRenderer();
        $renderer->sortableColumnClass = 'sortable';
        $renderer->sortableColumnClassActiveAsc = 'on up';
        $renderer->sortableColumnClassActiveDesc = 'on down';
        $renderer->sortLinkClassActiveAsc = 'link-up';
        $renderer->sortLinkClassActiveDesc = 'link-down';
        $renderer->sortableColumnLabelAddition = ' -';
        $renderer->sortableColumnLabelAdditionActiveAsc = ' ^';
        $renderer->sortableColumnLabelAdditionActiveDesc = ' v';

        $html = $this->createTable(sortableTableHeadRenderer: $renderer)->render();
        $this->assertStringContainsString(
            '<th scope="col" class="sort on up"><a href="?sort=users|id|DESC" class="link-up">Id ^</a></th>',
            $html,
        );
        $this->assertStringContainsString(
            '<th scope="col" class="sort sortable"><a href="?sort=users|name|ASC">Name -</a></th>',
            $html,
        );

        $html = $this->createTable(query: ['sort' => 'users|id|DESC'], sortableTableHeadRenderer: $renderer)->render();
        $this->assertStringContainsString(
            '<th scope="col" class="sort on down"><a href="?sort=users|id|ASC" class="link-down">Id v</a></th>',
            $html,
        );
    }

    public function testFilterConditionsRestrictTheRows(): void
    {
        $query = ['f' => '', 'find' => ''];
        $post = ['csrftoken' => 'expected-token', 'f_name' => 'e', 'f_age' => 'old'];
        $httpRequest = HttpRequestFactory::create(
            method: RequestMethodEnum::POST,
            queryParameters: $query,
            postParameters: $post,
        );
        $table = $this->createTable(
            tableFilter: $this->createFilter(httpRequest: $httpRequest),
            httpRequest: $httpRequest,
        );

        $html = $table->render();

        $this->assertSame(['Cleo'], DbResultTableRenderTest::names(html: $html));
        $this->assertStringContainsString('<strong>1</strong> Resultat', $html);
        $this->assertStringContainsString(
            '<input type="text" class="text" name="f_name" id="filter-f_name" value="e">',
            $html,
        );
        $this->assertStringContainsString('<option value="old" selected>', $html);
    }

    public function testCellValuesAreNotReadAsPlaceholdersOfTheTable(): void
    {
        $db = SqliteDatabase::create();
        $db->execute(
            sql: 'UPDATE users SET name = ? WHERE id = 1',
            parameters: ['[pagination][filter][footer][totalAmount]'],
        );
        $table = $this->createTable(itemsPerPage: 2, db: $db);

        $html = $table->render();

        $this->assertStringContainsString('<td>[pagination][filter][footer][totalAmount]</td>', $html);
        $this->assertSame(2, substr_count(haystack: $html, needle: '<div class="pagination">'));
    }

    public function testAdditionalLinkParametersAreEncodedInSortAndPageLinks(): void
    {
        $table = $this->createTable(itemsPerPage: 2);
        $table->addAdditionalLinkParameter(key: 'q', value: '"><script>x</script> &');

        $html = $table->render();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString(
            '<a href="?sort=users|name|ASC&q=%22%3E%3Cscript%3Ex%3C%2Fscript%3E+%26">Name</a>',
            $html,
        );
        $this->assertStringContainsString(
            '<a href="?page=2|users&q=%22%3E%3Cscript%3Ex%3C%2Fscript%3E+%26">2</a>',
            $html,
        );
        $this->assertSame(['q' => '"><script>x</script> &'], $table->additionalLinkParameters);
    }

    public function testIdentifiersInLinksAreEncoded(): void
    {
        $table = new DbResultTable(
            identifier: 'a b"c',
            db: SqliteDatabase::create(),
            dbQuery: DbQuery::createFromSqlQuery(query: 'SELECT id, name, age FROM users'),
            templateEngine: $this->templateEngine,
            httpRequest: HttpRequestFactory::create(),
            session: $this->session,
            itemsPerPage: 2,
        );
        $table->addColumn(
            abstractTableColumn: TableHelper::createDefaultColumn(identifier: 'name', label: 'Name', isSortable: true),
        );

        $this->assertStringContainsString('<a href="?sort=a+b%22c|name|DESC">Name</a>', $table->render());
    }

    public function testPageThatCannotExistIsIgnored(): void
    {
        $table = $this->createTable(query: ['page' => '99999999999999999999|users'], itemsPerPage: 2);

        $this->assertSame(['Anna', 'Ben'], DbResultTableRenderTest::names(html: $table->render()));
        $this->assertSame(1, $table->getCurrentPaginationPage());
    }

    public function testPageBeyondTheEndShowsNoRowsButTheWayBack(): void
    {
        $table = $this->createTable(query: ['page' => '9|users'], itemsPerPage: 2);

        $html = $table->render();

        $this->assertSame([], DbResultTableRenderTest::names(html: $html));
        $this->assertSame(3, $table->getTotalAmount());
        $this->assertMatchesRegularExpression('#<li class="back">\s*<a href="\?page=2\|users">#', $html);
    }

    public function testItemsPerPageMustBePositive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Items per page of table "users" must be at least 1, 0 given.');
        $this->createTable(itemsPerPage: 0);
    }
}
