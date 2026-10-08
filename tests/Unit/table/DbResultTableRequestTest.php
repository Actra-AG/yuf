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
use actra\yuf\db\FrameworkDb;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\session\SessionSectionEnum;
use actra\yuf\table\column\DefaultColumn;
use actra\yuf\table\table\DbResultTable;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\template\TemplateEngineFactory;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * Sorting and paging of a `DbResultTable` come from the query string of its request (never from the posted data) and
 * are kept in the session.
 */
final class DbResultTableRequestTest extends TestCase
{
    private int $counter = 0;
    private ArraySessionStorage $storage;
    private Session $session;

    #[Override]
    protected function setUp(): void
    {
        $this->storage = new ArraySessionStorage();
        $this->session = new Session(storage: $this->storage);
    }

    /**
     * A different identifier for every call, so tests can tell tables apart.
     */
    private function nextIdentifier(): string
    {
        return 'items' . ++$this->counter;
    }

    private function createTable(string $identifier, HttpRequest $httpRequest): DbResultTable
    {
        $table = new DbResultTable(
            identifier: $identifier,
            db: DbResultTableRequestTest::createStub(FrameworkDb::class),
            dbQuery: DbResultTableRequestTest::createStub(DbQuery::class),
            templateEngine: TemplateEngineFactory::create(
                cacheDirectory: sys_get_temp_dir() . '/yuf-db-result-table-test/',
                templateBaseDirectory: sys_get_temp_dir() . '/',
            ),
            httpRequest: $httpRequest,
            session: $this->session,
        );
        $table->addColumn(abstractTableColumn: new DefaultColumn(identifier: 'id', label: 'Id', isSortable: true));
        $table->addColumn(abstractTableColumn: new DefaultColumn(identifier: 'name', label: 'Name', isSortable: true));

        return $table;
    }

    public function testDefaultSortingIsTheFirstColumnAscending(): void
    {
        $table = $this->createTable(identifier: $this->nextIdentifier(), httpRequest: HttpRequestFactory::create());

        $table->fillBySelectQuery();

        $this->assertSame('id', $table->getCurrentSortColumn());
        $this->assertSame('ASC', $table->getCurrentSortDirection());
        $this->assertSame(1, $table->getCurrentPaginationPage());
    }

    public function testSortingIsReadFromTheQueryString(): void
    {
        $id = $this->nextIdentifier();
        $httpRequest = HttpRequestFactory::create(queryParameters: ['sort' => $id . '|name|DESC']);
        $table = $this->createTable(identifier: $id, httpRequest: $httpRequest);

        $table->fillBySelectQuery();

        $this->assertSame('name', $table->getCurrentSortColumn());
        $this->assertSame('DESC', $table->getCurrentSortDirection());
    }

    public function testSortingOfAPostRequestBodyIsIgnored(): void
    {
        $id = $this->nextIdentifier();
        $httpRequest = HttpRequestFactory::create(
            method: RequestMethodEnum::POST,
            postParameters: ['sort' => $id . '|name|DESC'],
        );
        $table = $this->createTable(identifier: $id, httpRequest: $httpRequest);

        $table->fillBySelectQuery();

        $this->assertSame('id', $table->getCurrentSortColumn());
    }

    public function testSortingOfAnotherTableOrColumnIsIgnored(): void
    {
        $otherId = $this->nextIdentifier();
        $otherTable = $this->createTable(
            identifier: $otherId,
            httpRequest: HttpRequestFactory::create(queryParameters: ['sort' => 'unrelated|name|DESC']),
        );
        $unknownId = $this->nextIdentifier();
        $unknownColumn = $this->createTable(
            identifier: $unknownId,
            httpRequest: HttpRequestFactory::create(queryParameters: ['sort' => $unknownId . '|nope|DESC']),
        );

        $otherTable->fillBySelectQuery();
        $unknownColumn->fillBySelectQuery();

        $this->assertSame('id', $otherTable->getCurrentSortColumn());
        $this->assertSame('id', $unknownColumn->getCurrentSortColumn());
    }

    public function testPageIsReadFromTheQueryString(): void
    {
        $id = $this->nextIdentifier();
        $table = $this->createTable(
            identifier: $id,
            httpRequest: HttpRequestFactory::create(queryParameters: ['page' => '3|' . $id]),
        );

        $table->fillBySelectQuery();

        $this->assertSame(3, $table->getCurrentPaginationPage());
    }

    public function testPageOfAnotherTableOrOfThePostDataIsIgnored(): void
    {
        $otherTable = $this->createTable(
            identifier: $this->nextIdentifier(),
            httpRequest: HttpRequestFactory::create(queryParameters: ['page' => '3|unrelated']),
        );
        $postedId = $this->nextIdentifier();
        $posted = $this->createTable(
            identifier: $postedId,
            httpRequest: HttpRequestFactory::create(postParameters: ['page' => '3|' . $postedId]),
        );

        $otherTable->fillBySelectQuery();
        $posted->fillBySelectQuery();

        $this->assertSame(1, $otherTable->getCurrentPaginationPage());
        $this->assertSame(1, $posted->getCurrentPaginationPage());
    }

    public function testFindAndResetGoBackToTheFirstPage(): void
    {
        foreach (['find', 'reset'] as $parameter) {
            $id = $this->nextIdentifier();
            $this->session->setSection(
                section: SessionSectionEnum::TABLES,
                data: [$id => ['paginationPage' => '5', 'sortColumn' => 'name', 'sortDirection' => 'DESC']],
            );
            $table = $this->createTable(
                identifier: $id,
                httpRequest: HttpRequestFactory::create(queryParameters: [$parameter => '']),
            );

            $table->fillBySelectQuery();

            $this->assertSame(1, $table->getCurrentPaginationPage(), $parameter);
        }
    }

    public function testResetRestoresTheDefaultSorting(): void
    {
        $id = $this->nextIdentifier();
        $this->session->setSection(
            section: SessionSectionEnum::TABLES,
            data: [$id => ['sortColumn' => 'name', 'sortDirection' => 'DESC']],
        );
        $table = $this->createTable(
            identifier: $id,
            httpRequest: HttpRequestFactory::create(queryParameters: ['reset' => '']),
        );

        $table->fillBySelectQuery();

        $this->assertSame('id', $table->getCurrentSortColumn());
        $this->assertSame('ASC', $table->getCurrentSortDirection());
    }

    public function testSortingOfAPreviousRequestStaysInTheSession(): void
    {
        $id = $this->nextIdentifier();
        $this->session->setSection(
            section: SessionSectionEnum::TABLES,
            data: [$id => ['sortColumn' => 'name', 'sortDirection' => 'DESC']],
        );
        $table = $this->createTable(identifier: $id, httpRequest: HttpRequestFactory::create());

        $table->fillBySelectQuery();

        $this->assertSame('name', $table->getCurrentSortColumn());
        $this->assertSame('DESC', $table->getCurrentSortDirection());
    }
}
