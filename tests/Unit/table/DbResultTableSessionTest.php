<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\table;

use actra\yuf\db\DbQuery;
use actra\yuf\db\FrameworkDb;
use actra\yuf\session\AbstractSessionHandler;
use actra\yuf\table\column\DefaultColumn;
use actra\yuf\table\renderer\TableHeadRenderer;
use actra\yuf\table\table\DbResultTable;
use actra\yuf\table\table\SmartTable;
use actra\yuf\table\TableItemCollection;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\table\StaticTableRegistries;
use actra\yuf\tests\Double\template\TemplateEngineFactory;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * Characterization of the session behaviour before the redesign (docs/session/plan.md, step 1): what a
 * `DbResultTable` remembers across requests (sorting, page), when it forgets it, and the static
 * `saveToSession()` / `getFromSession()`. `DbResultTableRequestTest` covers where the input comes from; this class
 * covers the session.
 *
 * A request is simulated by building the table again with the same identifier and the `$_SESSION` of the previous
 * one. The static identifier registries have no reset method, so `StaticTableRegistries::reset()` empties them
 * through reflection (removed in step 2 together with the registries).
 *
 * The behaviour tests only use `request()`, `seedState()` and `storedState()`; the keys and value shapes of the
 * storage are pinned in `testStorageLayout…()` only (they may change in step 2).
 */
final class DbResultTableSessionTest extends TestCase
{
    private const string ID = 'sessionItems';

    #[Override]
    protected function setUp(): void
    {
        StaticTableRegistries::reset();
        $_SESSION = [];
    }

    #[Override]
    protected function tearDown(): void
    {
        StaticTableRegistries::reset();
        unset($_SESSION); // Sessions are disabled in the CLI
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
            dbQuery: DbResultTableSessionTest::createStub(DbQuery::class),
            templateEngine: TemplateEngineFactory::create(
                cacheDirectory: sys_get_temp_dir() . '/yuf-db-result-table-test/',
                templateBaseDirectory: sys_get_temp_dir() . '/',
            ),
            httpRequest: HttpRequestFactory::create(queryParameters: $query, postParameters: $post),
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
        StaticTableRegistries::reset();
        $table = $this->createTable(identifier: $identifier, query: $query);
        $table->fillBySelectQuery();

        return $table;
    }

    /**
     * @param array<string, string> $state sort_column, sort_direction, pagination_page
     */
    private function seedState(array $state, string $identifier = DbResultTableSessionTest::ID): void
    {
        $_SESSION['table'] = [$identifier => $state];
    }

    /**
     * @return array<string, string>
     */
    private function storedState(string $identifier = DbResultTableSessionTest::ID): array
    {
        $tables = $_SESSION['table'] ?? [];
        $state = is_array(value: $tables) ? $tables[$identifier] ?? [] : [];
        $result = [];
        foreach (is_array(value: $state) ? $state : [] as $index => $value) {
            if (is_string(value: $index) && is_string(value: $value)) {
                $result[$index] = $value;
            }
        }

        return $result;
    }

    private function sortParameter(string $column, string $direction, string $identifier = DbResultTableSessionTest::ID): string
    {
        return $identifier . '|' . $column . '|' . $direction;
    }

    public function testStorageLayoutIsOneArrayPerTableBelowTheKeyTable(): void
    {
        $this->request(query: ['sort' => $this->sortParameter(column: 'name', direction: 'DESC'), 'page' => '3|' . DbResultTableSessionTest::ID]);

        $this->assertSame(
            [
                'table' => [
                    DbResultTableSessionTest::ID => [
                        'sort_column' => 'name',
                        'sort_direction' => 'DESC',
                        'pagination_page' => '3',
                    ],
                ],
            ],
            $_SESSION,
        );
    }

    public function testStorageLayoutOfTheDefaultStateIsWrittenOnReadAndPageIsAString(): void
    {
        $this->request();

        $this->assertSame(
            ['sort_column' => 'id', 'sort_direction' => 'ASC', 'pagination_page' => '1'],
            $this->storedState(),
        );
    }

    public function testStorageLayoutOfTheStaticAccessors(): void
    {
        DbResultTable::saveToSession(dataType: 'custom', identifier: 'one', index: 'key', value: 'value');

        $this->assertSame(['custom' => ['one' => ['key' => 'value']]], $_SESSION);
    }

    public function testFirstColumnAscendingIsTheDefaultSorting(): void
    {
        $table = $this->request();

        $this->assertSame('id', $table->getCurrentSortColumn());
        $this->assertSame('ASC', $table->getCurrentSortDirection());
    }

    public function testDefaultSortColumnAndItsDirectionAreUsedWithoutChoice(): void
    {
        StaticTableRegistries::reset();
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
        $this->assertSame('DESC', $table->getCurrentSortDirection());
    }

    public function testChosenSortingIsRememberedForTheNextRequests(): void
    {
        $this->request(query: ['sort' => $this->sortParameter(column: 'name', direction: 'DESC')]);

        $next = $this->request();
        $afterwards = $this->request();

        $this->assertSame('name', $next->getCurrentSortColumn());
        $this->assertSame('DESC', $next->getCurrentSortDirection());
        $this->assertSame('name', $afterwards->getCurrentSortColumn());
        $this->assertSame('DESC', $afterwards->getCurrentSortDirection());
    }

    public function testNewSortingReplacesTheRememberedOne(): void
    {
        $this->request(query: ['sort' => $this->sortParameter(column: 'name', direction: 'DESC')]);

        $table = $this->request(query: ['sort' => $this->sortParameter(column: 'created', direction: 'ASC')]);

        $this->assertSame('created', $table->getCurrentSortColumn());
        $this->assertSame('ASC', $table->getCurrentSortDirection());
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
            $this->assertSame('DESC', $table->getCurrentSortDirection(), $invalidSorting);
        }
    }

    public function testResetForgetsTheChosenSorting(): void
    {
        $this->request(query: ['sort' => $this->sortParameter(column: 'name', direction: 'DESC')]);

        $reset = $this->request(query: ['reset' => '']);
        $next = $this->request();

        $this->assertSame('id', $reset->getCurrentSortColumn());
        $this->assertSame('ASC', $reset->getCurrentSortDirection());
        $this->assertSame('id', $next->getCurrentSortColumn());
        $this->assertSame('ASC', $next->getCurrentSortDirection());
    }

    public function testResetWinsOverASortingOfTheSameRequest(): void
    {
        $table = $this->request(
            query: ['reset' => '', 'sort' => $this->sortParameter(column: 'name', direction: 'DESC')],
        );

        $this->assertSame('id', $table->getCurrentSortColumn());
        $this->assertSame('ASC', $table->getCurrentSortDirection());
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
        $this->request(query: ['sort' => $this->sortParameter(column: 'name', direction: 'DESC'), 'page' => '3|' . DbResultTableSessionTest::ID]);

        $other = $this->request(identifier: 'otherItems');

        $this->assertSame('id', $other->getCurrentSortColumn());
        $this->assertSame(1, $other->getCurrentPaginationPage());
        $this->assertSame('name', $this->request()->getCurrentSortColumn());
    }

    public function testStateOfAnotherSessionStartsFromTheDefaults(): void
    {
        $this->request(query: ['sort' => $this->sortParameter(column: 'name', direction: 'DESC'), 'page' => '3|' . DbResultTableSessionTest::ID]);
        $_SESSION = [];

        $table = $this->request();

        $this->assertSame('id', $table->getCurrentSortColumn());
        $this->assertSame(1, $table->getCurrentPaginationPage());
    }

    public function testStateIsGoneAfterTheUserDataWasCleared(): void
    {
        $this->seedState(state: ['sort_column' => 'name', 'sort_direction' => 'DESC', 'pagination_page' => '3']);

        AbstractSessionHandler::clearUserData();

        $this->assertSame([], $this->storedState());
        $this->assertSame('id', $this->request()->getCurrentSortColumn());
    }

    public function testRememberedStateIsUsedAsStored(): void
    {
        $this->seedState(state: ['sort_column' => 'created', 'sort_direction' => 'ASC', 'pagination_page' => '7']);

        $table = $this->request();

        $this->assertSame('created', $table->getCurrentSortColumn());
        $this->assertSame('ASC', $table->getCurrentSortDirection());
        $this->assertSame(7, $table->getCurrentPaginationPage());
    }

    public function testRememberedPageBelowOneGoesBackToTheFirstPage(): void
    {
        $this->seedState(state: ['sort_column' => 'id', 'sort_direction' => 'ASC', 'pagination_page' => '0']);

        $this->assertSame(1, $this->request()->getCurrentPaginationPage());
    }

    public function testSetCurrentPaginationPageIsRemembered(): void
    {
        $table = $this->request();

        $table->setCurrentPaginationPage(page: 6);

        $this->assertSame(6, $table->getCurrentPaginationPage());
        $this->assertSame(6, $this->request()->getCurrentPaginationPage());
    }

    public function testSortDirectionBeforeTheTableWasFilledIsAnError(): void
    {
        $table = $this->createTable(identifier: DbResultTableSessionTest::ID);

        $this->expectException(TypeError::class);

        $table->getCurrentSortDirection();
    }

    public function testStateIsNotReadFromThePostedData(): void
    {
        $table = $this->createTable(
            identifier: DbResultTableSessionTest::ID,
            post: ['sort' => $this->sortParameter(column: 'name', direction: 'DESC'), 'page' => '3|' . DbResultTableSessionTest::ID],
        );

        $table->fillBySelectQuery();

        $this->assertSame('id', $table->getCurrentSortColumn());
        $this->assertSame(1, $table->getCurrentPaginationPage());
    }

    public function testStaticAccessorsReturnWhatWasSaved(): void
    {
        DbResultTable::saveToSession(dataType: 'custom', identifier: 'one', index: 'key', value: 'value');

        $this->assertSame('value', DbResultTable::getFromSession(dataType: 'custom', identifier: 'one', index: 'key'));
    }

    public function testStaticAccessorsOverwriteAndKeepOtherIndexes(): void
    {
        DbResultTable::saveToSession(dataType: 'custom', identifier: 'one', index: 'a', value: '1');
        DbResultTable::saveToSession(dataType: 'custom', identifier: 'one', index: 'b', value: '2');
        DbResultTable::saveToSession(dataType: 'custom', identifier: 'one', index: 'a', value: '3');

        $this->assertSame('3', DbResultTable::getFromSession(dataType: 'custom', identifier: 'one', index: 'a'));
        $this->assertSame('2', DbResultTable::getFromSession(dataType: 'custom', identifier: 'one', index: 'b'));
    }

    public function testStaticAccessorsKeepDataTypesAndIdentifiersApart(): void
    {
        DbResultTable::saveToSession(dataType: 'custom', identifier: 'one', index: 'key', value: 'value');

        $this->assertNull(DbResultTable::getFromSession(dataType: 'other', identifier: 'one', index: 'key'));
        $this->assertNull(DbResultTable::getFromSession(dataType: 'custom', identifier: 'two', index: 'key'));
    }

    public function testStaticReadOfUnknownDataReturnsNullAndWritesAnEmptyArray(): void
    {
        $this->assertNull(DbResultTable::getFromSession(dataType: 'custom', identifier: 'one', index: 'key'));

        $this->assertSame(['custom' => ['one' => []]], $_SESSION);
    }

    public function testTableWorksWithoutSessionWithinTheRequest(): void
    {
        unset($_SESSION);

        $table = $this->createTable(
            identifier: DbResultTableSessionTest::ID,
            query: ['sort' => $this->sortParameter(column: 'name', direction: 'DESC'), 'page' => '2|' . DbResultTableSessionTest::ID],
        );
        $table->fillBySelectQuery();

        $this->assertSame('name', $table->getCurrentSortColumn());
        $this->assertSame('DESC', $table->getCurrentSortDirection());
        $this->assertSame(2, $table->getCurrentPaginationPage());
    }

    public function testWithoutSessionNothingIsRememberedForTheNextRequest(): void
    {
        unset($_SESSION);
        $first = $this->createTable(
            identifier: DbResultTableSessionTest::ID,
            query: ['sort' => $this->sortParameter(column: 'name', direction: 'DESC')],
        );
        $first->fillBySelectQuery();
        unset($_SESSION); // the next request starts without the data of this one

        $table = $this->request();

        $this->assertSame('id', $table->getCurrentSortColumn());
    }

    /**
     * Removed in step 2 together with the static registry of `SmartTable`.
     */
    public function testSecondTableWithTheSameIdentifierThrows(): void
    {
        $this->createTable(identifier: DbResultTableSessionTest::ID);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('There is already a table with the same identifier ' . DbResultTableSessionTest::ID);

        $this->createTable(identifier: DbResultTableSessionTest::ID);
    }

    /**
     * Removed in step 2 together with the static registry of `SmartTable`.
     */
    public function testSmartTableAndDbResultTableShareTheIdentifierRegistry(): void
    {
        $this->createTable(identifier: DbResultTableSessionTest::ID);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('There is already a table with the same identifier ' . DbResultTableSessionTest::ID);

        new SmartTable(
            identifier: DbResultTableSessionTest::ID,
            tableHeadRenderer: new TableHeadRenderer(),
            tableItemCollection: new TableItemCollection(),
        );
    }
}
