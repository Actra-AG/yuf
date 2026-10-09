<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\table;

use actra\yuf\db\DbQuery;
use actra\yuf\db\FrameworkDb;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\session\SessionSectionEnum;
use actra\yuf\table\column\DefaultColumn;
use actra\yuf\table\renderer\TableHeadRenderer;
use actra\yuf\table\table\DbResultTable;
use actra\yuf\table\table\SmartTable;
use actra\yuf\table\TableItemCollection;
use actra\yuf\table\TableSortDirectionEnum;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\template\TemplateEngineFactory;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * What a `DbResultTable` remembers across requests (sorting, page) in `yuf.tables.<identifier>`, when it forgets it,
 * and that reading never writes. `DbResultTableRequestTest` covers where the input comes from; this class covers the
 * session.
 *
 * A request is simulated by building the table again with the same identifier and the session of the previous one.
 * The behaviour tests only use `request()`, `seedState()` and `storedState()`; the keys and value shapes of the
 * storage are pinned in `testStorageLayout…()` only.
 */
final class DbResultTableSessionTest extends TestCase
{
    private const string ID = 'sessionItems';

    private ArraySessionStorage $storage;
    private Session $session;

    #[Override]
    protected function setUp(): void
    {
        $this->storage = new ArraySessionStorage();
        $this->session = new Session(storage: $this->storage);
    }

    /**
     * @param array<string, string> $query
     * @param array<string, string> $post
     */
    private function createTable(string $identifier, array $query = [], array $post = []): DbResultTable
    {
        $table = new DbResultTable(
            identifier: $identifier,
            db: DbResultTableSessionTest::createStub(FrameworkDb::class),
            dbQuery: DbQuery::createFromSqlQuery(query: 'SELECT id FROM item'),
            templateEngine: TemplateEngineFactory::create(
                cacheDirectory: sys_get_temp_dir() . '/yuf-db-result-table-test/',
                templateBaseDirectory: sys_get_temp_dir() . '/',
            ),
            httpRequest: HttpRequestFactory::create(queryParameters: $query, postParameters: $post),
            session: $this->session,
        );
        $table->addColumn(abstractTableColumn: new DefaultColumn(identifier: 'id', label: 'Id', isSortable: true));
        $table->addColumn(abstractTableColumn: new DefaultColumn(identifier: 'name', label: 'Name', isSortable: true));
        $table->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'created',
                label: 'Created',
                isSortable: true,
                sortAscendingByDefault: false,
            ),
            isDefaultSortColumn: false,
        );

        return $table;
    }

    /**
     * The next request of the user: builds and fills the table with the session of the previous request.
     *
     * @param array<string, string> $query
     */
    private function request(array $query = [], string $identifier = DbResultTableSessionTest::ID): DbResultTable
    {
        $table = $this->createTable(identifier: $identifier, query: $query);
        $table->fillBySelectQuery();

        return $table;
    }

    /**
     * @param array<string, string> $state sortColumn, sortDirection, paginationPage
     */
    private function seedState(array $state, string $identifier = DbResultTableSessionTest::ID): void
    {
        $this->session->setSection(section: SessionSectionEnum::TABLES, data: [$identifier => $state]);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function storedState(string $identifier = DbResultTableSessionTest::ID): array
    {
        $state = $this->session->getSection(section: SessionSectionEnum::TABLES)[$identifier] ?? [];

        return is_array(value: $state) ? $state : [];
    }

    private function sortParameter(
        string $column,
        string $direction,
        string $identifier = DbResultTableSessionTest::ID,
    ): string {
        return $identifier . '|' . $column . '|' . $direction;
    }

    public function testStorageLayoutIsOneArrayPerTableInTheTablesSection(): void
    {
        $this->request(query: [
            'sort' => $this->sortParameter(column: 'name', direction: 'DESC'),
            'page' => '3|' . DbResultTableSessionTest::ID,
        ]);

        $this->assertSame(
            [
                'yuf' => [
                    'tables' => [
                        DbResultTableSessionTest::ID => [
                            'sortColumn' => 'name',
                            'sortDirection' => 'DESC',
                            'paginationPage' => '3',
                        ],
                    ],
                ],
            ],
            $this->storage->all(),
        );
    }

    /**
     * Fix of v4.30.0: before, the default sorting and the first page were written on the first request.
     */
    public function testDefaultStateIsNotWrittenIntoTheSession(): void
    {
        $this->request();
        $this->request(query: ['find' => '']);

        $this->assertSame([], $this->storage->all());
    }

    public function testPageIsStoredAsString(): void
    {
        $this->request(query: ['page' => '3|' . DbResultTableSessionTest::ID]);

        $this->assertSame(['paginationPage' => '3'], $this->storedState());
    }

    public function testFirstColumnAscendingIsTheDefaultSorting(): void
    {
        $table = $this->request();

        $this->assertSame('id', $table->getCurrentSortColumn());
        $this->assertSame(TableSortDirectionEnum::ASC, $table->getCurrentSortDirection());
    }

    public function testDefaultSortColumnAndItsDirectionAreUsedWithoutChoice(): void
    {
        $table = $this->createTable(identifier: DbResultTableSessionTest::ID);
        $table->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'score',
                label: 'Score',
                isSortable: true,
                sortAscendingByDefault: false,
            ),
            isDefaultSortColumn: true,
        );

        $table->fillBySelectQuery();

        $this->assertSame('score', $table->getCurrentSortColumn());
        $this->assertSame(TableSortDirectionEnum::DESC, $table->getCurrentSortDirection());
    }

    public function testChosenSortingIsRememberedForTheNextRequests(): void
    {
        $this->request(query: ['sort' => $this->sortParameter(column: 'name', direction: 'DESC')]);

        $next = $this->request();
        $afterwards = $this->request();

        $this->assertSame('name', $next->getCurrentSortColumn());
        $this->assertSame(TableSortDirectionEnum::DESC, $next->getCurrentSortDirection());
        $this->assertSame('name', $afterwards->getCurrentSortColumn());
        $this->assertSame(TableSortDirectionEnum::DESC, $afterwards->getCurrentSortDirection());
    }

    public function testNewSortingReplacesTheRememberedOne(): void
    {
        $this->request(query: ['sort' => $this->sortParameter(column: 'name', direction: 'DESC')]);

        $table = $this->request(query: ['sort' => $this->sortParameter(column: 'created', direction: 'ASC')]);

        $this->assertSame('created', $table->getCurrentSortColumn());
        $this->assertSame(TableSortDirectionEnum::ASC, $table->getCurrentSortDirection());
    }

    public function testInvalidSortingKeepsTheRememberedOne(): void
    {
        $this->request(query: ['sort' => $this->sortParameter(column: 'name', direction: 'DESC')]);
        $invalidSortings = [
            $this->sortParameter(column: 'name', direction: 'SIDEWAYS'),
            $this->sortParameter(column: 'unknown', direction: 'ASC'),
            $this->sortParameter(column: 'id', direction: 'ASC', identifier: 'otherTable'),
            'sessionItems|id',
            'sessionItems|id|ASC|x',
            '',
        ];

        foreach ($invalidSortings as $invalidSorting) {
            $table = $this->request(query: ['sort' => $invalidSorting]);

            $this->assertSame('name', $table->getCurrentSortColumn(), $invalidSorting);
            $this->assertSame(TableSortDirectionEnum::DESC, $table->getCurrentSortDirection(), $invalidSorting);
        }
    }

    public function testResetForgetsTheChosenSorting(): void
    {
        $this->request(query: ['sort' => $this->sortParameter(column: 'name', direction: 'DESC')]);

        $reset = $this->request(query: ['reset' => '']);
        $next = $this->request();

        $this->assertSame('id', $reset->getCurrentSortColumn());
        $this->assertSame(TableSortDirectionEnum::ASC, $reset->getCurrentSortDirection());
        $this->assertSame('id', $next->getCurrentSortColumn());
        $this->assertSame(TableSortDirectionEnum::ASC, $next->getCurrentSortDirection());
    }

    public function testResetWinsOverASortingOfTheSameRequest(): void
    {
        $table = $this->request(
            query: ['reset' => '', 'sort' => $this->sortParameter(column: 'name', direction: 'DESC')],
        );

        $this->assertSame('id', $table->getCurrentSortColumn());
        $this->assertSame(TableSortDirectionEnum::ASC, $table->getCurrentSortDirection());
    }

    public function testFindKeepsTheChosenSorting(): void
    {
        $this->request(query: ['sort' => $this->sortParameter(column: 'name', direction: 'DESC')]);

        $table = $this->request(query: ['find' => '']);

        $this->assertSame('name', $table->getCurrentSortColumn());
    }

    public function testPageIsRememberedForTheNextRequests(): void
    {
        $this->request(query: ['page' => '3|' . DbResultTableSessionTest::ID]);

        $this->assertSame(3, $this->request()->getCurrentPaginationPage());
        $this->assertSame(3, $this->request()->getCurrentPaginationPage());
    }

    public function testInvalidPageKeepsTheRememberedOne(): void
    {
        $this->request(query: ['page' => '3|' . DbResultTableSessionTest::ID]);

        foreach (['0|sessionItems', '-2|sessionItems', 'abc|sessionItems', '5|otherTable', '5', ''] as $page) {
            $this->assertSame(
                3,
                $this->request(query: ['page' => $page])->getCurrentPaginationPage(),
                $page,
            );
        }
    }

    public function testFindAndResetGoBackToTheFirstPageForTheNextRequestsToo(): void
    {
        foreach (['find', 'reset'] as $parameter) {
            $this->request(query: ['page' => '4|' . DbResultTableSessionTest::ID]);

            $this->request(query: [$parameter => '']);

            $this->assertSame(1, $this->request()->getCurrentPaginationPage(), $parameter);
        }
    }

    public function testChangingTheSortingKeepsThePage(): void
    {
        $this->request(query: ['page' => '4|' . DbResultTableSessionTest::ID]);

        $table = $this->request(query: ['sort' => $this->sortParameter(column: 'name', direction: 'DESC')]);

        $this->assertSame(4, $table->getCurrentPaginationPage());
    }

    public function testStateIsKeptPerTable(): void
    {
        $this->request(query: [
            'sort' => $this->sortParameter(column: 'name', direction: 'DESC'),
            'page' => '3|' . DbResultTableSessionTest::ID,
        ]);

        $other = $this->request(identifier: 'otherItems');

        $this->assertSame('id', $other->getCurrentSortColumn());
        $this->assertSame(1, $other->getCurrentPaginationPage());
        $this->assertSame('name', $this->request()->getCurrentSortColumn());
    }

    public function testStateOfAnotherSessionStartsFromTheDefaults(): void
    {
        $this->request(query: [
            'sort' => $this->sortParameter(column: 'name', direction: 'DESC'),
            'page' => '3|' . DbResultTableSessionTest::ID,
        ]);
        $this->storage->replaceAll(data: []);

        $table = $this->request();

        $this->assertSame('id', $table->getCurrentSortColumn());
        $this->assertSame(1, $table->getCurrentPaginationPage());
    }

    public function testStateIsGoneAfterTheUserDataWasCleared(): void
    {
        $this->seedState(state: ['sortColumn' => 'name', 'sortDirection' => 'DESC', 'paginationPage' => '3']);

        $this->session->clearUserData();

        $this->assertSame([], $this->storedState());
        $this->assertSame('id', $this->request()->getCurrentSortColumn());
    }

    public function testRememberedStateIsUsedAsStored(): void
    {
        $this->seedState(state: ['sortColumn' => 'created', 'sortDirection' => 'ASC', 'paginationPage' => '7']);

        $table = $this->request();

        $this->assertSame('created', $table->getCurrentSortColumn());
        $this->assertSame(TableSortDirectionEnum::ASC, $table->getCurrentSortDirection());
        $this->assertSame(7, $table->getCurrentPaginationPage());
    }

    public function testRememberedPageBelowOneGoesBackToTheFirstPage(): void
    {
        $this->seedState(state: ['sortColumn' => 'id', 'sortDirection' => 'ASC', 'paginationPage' => '0']);

        $this->assertSame(1, $this->request()->getCurrentPaginationPage());
    }

    public function testSetCurrentPaginationPageIsRemembered(): void
    {
        $table = $this->request();

        $table->setCurrentPaginationPage(page: 6);

        $this->assertSame(6, $table->getCurrentPaginationPage());
        $this->assertSame(6, $this->request()->getCurrentPaginationPage());
    }

    /**
     * Fix of v4.30.0: before, the direction of a table that was not filled yet was a `TypeError`.
     */
    public function testSortColumnAndDirectionBeforeTheTableWasFilledAreTheDefaults(): void
    {
        $table = $this->createTable(identifier: DbResultTableSessionTest::ID);

        $this->assertSame('id', $table->getCurrentSortColumn());
        $this->assertSame(TableSortDirectionEnum::ASC, $table->getCurrentSortDirection());
        $this->assertSame(1, $table->getCurrentPaginationPage());
        $this->assertSame([], $this->storage->all());
    }

    public function testTableWithoutColumnsHasNoSortColumn(): void
    {
        $table = new DbResultTable(
            identifier: DbResultTableSessionTest::ID,
            db: DbResultTableSessionTest::createStub(FrameworkDb::class),
            dbQuery: DbQuery::createFromSqlQuery(query: 'SELECT id FROM item'),
            templateEngine: TemplateEngineFactory::create(
                cacheDirectory: sys_get_temp_dir() . '/yuf-db-result-table-test/',
                templateBaseDirectory: sys_get_temp_dir() . '/',
            ),
            httpRequest: HttpRequestFactory::create(),
            session: $this->session,
        );

        $this->assertNull($table->getCurrentSortColumn());
        $this->assertSame(TableSortDirectionEnum::ASC, $table->getCurrentSortDirection());
    }

    public function testStateIsNotReadFromThePostedData(): void
    {
        $table = $this->createTable(
            identifier: DbResultTableSessionTest::ID,
            post: [
                'sort' => $this->sortParameter(column: 'name', direction: 'DESC'),
                'page' => '3|' . DbResultTableSessionTest::ID,
            ],
        );

        $table->fillBySelectQuery();

        $this->assertSame('id', $table->getCurrentSortColumn());
        $this->assertSame(1, $table->getCurrentPaginationPage());
    }

    public function testResetRemovesTheStoredSorting(): void
    {
        $this->seedState(state: ['sortColumn' => 'name', 'sortDirection' => 'DESC', 'paginationPage' => '3']);

        $this->request(query: ['reset' => '']);

        $this->assertSame(['paginationPage' => '1'], $this->storedState());
    }

    public function testStateIsKeptInTheSessionOnlyForTheTablesOfTheRequest(): void
    {
        $this->request(query: ['sort' => $this->sortParameter(column: 'name', direction: 'DESC')]);
        $this->request(
            query: ['sort' => $this->sortParameter(column: 'id', direction: 'DESC', identifier: 'otherItems')],
            identifier: 'otherItems',
        );

        $this->assertSame(['sortColumn' => 'name', 'sortDirection' => 'DESC'], $this->storedState());
        $this->assertSame(
            ['sortColumn' => 'id', 'sortDirection' => 'DESC'],
            $this->storedState(identifier: 'otherItems'),
        );
    }

    /**
     * The identifier is the session key: tables of one page must have different identifiers (documented in
     * docs/session-and-login.md), tables with the same identifier share their state.
     */
    public function testTablesWithTheSameIdentifierShareTheirState(): void
    {
        $first = $this->createTable(identifier: DbResultTableSessionTest::ID);
        $second = $this->createTable(identifier: DbResultTableSessionTest::ID);

        $first->setCurrentPaginationPage(page: 4);

        $this->assertSame(4, $second->getCurrentPaginationPage());
    }

    public function testSmartTableNeedsNoSessionAndIdentifiersMayRepeat(): void
    {
        $first = new SmartTable(
            identifier: DbResultTableSessionTest::ID,
            tableHeadRenderer: new TableHeadRenderer(),
            tableItemCollection: new TableItemCollection(),
        );
        $second = new SmartTable(
            identifier: DbResultTableSessionTest::ID,
            tableHeadRenderer: new TableHeadRenderer(),
            tableItemCollection: new TableItemCollection(),
        );

        $this->assertNotSame($first, $second);
    }

    public function testSettingTheSamePageAgainDoesNotChangeTheSession(): void
    {
        $table = $this->request(query: ['page' => '3|' . DbResultTableSessionTest::ID]);
        $before = $this->storage->all();

        $table->setCurrentPaginationPage(page: 3);

        $this->assertSame($before, $this->storage->all());
    }
}
