<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\table\filter;

use actra\yuf\core\RequestMethodEnum;
use actra\yuf\db\DbQuery;
use actra\yuf\db\FrameworkDb;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\table\filter\TableFilter;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\security\InMemoryCsrfTokenSource;
use actra\yuf\tests\Double\table\FixedPageDbResultTable;
use actra\yuf\tests\Double\table\RecordingTableFilterField;
use actra\yuf\tests\Double\template\TemplateEngineFactory;
use PHPUnit\Framework\TestCase;

final class TableFilterCsrfTest extends TestCase
{
    private static int $instanceCounter = 0;

    /**
     * @param array<string, string> $post
     * @param array<string, string> $query
     */
    private function sendFilter(
        RequestMethodEnum $requestMethod,
        array $post,
        array $query = [],
        bool $withCsrfTokenSource = true,
    ): RecordingTableFilterField {
        $session = new Session(storage: new ArraySessionStorage());
        $identifier = 'csrfFilter' . ++TableFilterCsrfTest::$instanceCounter;
        $httpRequest = HttpRequestFactory::create(
            method: $requestMethod,
            queryParameters: [$identifier => ''] + $query,
            postParameters: $post,
        );
        $tableFilter = new TableFilter(
            identifier: $identifier,
            httpRequest: $httpRequest,
            session: $session,
            csrfTokenSource: $withCsrfTokenSource ? new InMemoryCsrfTokenSource(token: 'expected-token') : null,
        );
        $field = new RecordingTableFilterField(parentFilter: $tableFilter);
        $tableFilter->addPrimaryField(abstractTableFilterField: $field);

        $tableFilter->validate(
            dbResultTable: new FixedPageDbResultTable(
                identifier: $identifier . 'Table',
                db: TableFilterCsrfTest::createStub(FrameworkDb::class),
                dbQuery: DbQuery::createFromSqlQuery(query: 'SELECT id FROM item'),
                templateEngine: TemplateEngineFactory::create(
                    cacheDirectory: sys_get_temp_dir() . '/yuf-table-filter-test/',
                    templateBaseDirectory: sys_get_temp_dir() . '/',
                ),
                httpRequest: $httpRequest,
                session: $session,
                totalAmount: 0,
                currentPage: 1,
            ),
        );

        return $field;
    }

    public function testPostedFilterWithValidTokenIsApplied(): void
    {
        $field = $this->sendFilter(requestMethod: RequestMethodEnum::POST, post: ['csrftoken' => 'expected-token']);

        $this->assertTrue($field->inputChecked);
    }

    public function testPostedFilterWithWrongTokenIsIgnored(): void
    {
        $field = $this->sendFilter(requestMethod: RequestMethodEnum::POST, post: ['csrftoken' => 'wrong']);

        $this->assertFalse($field->inputChecked);
    }

    public function testPostedFilterWithoutTokenIsIgnored(): void
    {
        $field = $this->sendFilter(requestMethod: RequestMethodEnum::POST, post: []);

        $this->assertFalse($field->inputChecked);
    }

    public function testFilterWithTokenInTheUrlIsIgnored(): void
    {
        $field = $this->sendFilter(
            requestMethod: RequestMethodEnum::GET,
            post: [],
            query: ['csrftoken' => 'expected-token'],
        );

        $this->assertFalse($field->inputChecked);
    }

    public function testWithoutTokenSourceAPostedFilterIsApplied(): void
    {
        $field = $this->sendFilter(requestMethod: RequestMethodEnum::POST, post: [], withCsrfTokenSource: false);

        $this->assertTrue($field->inputChecked);
    }

    public function testWithoutTokenSourceAFilterInTheUrlIsStillIgnored(): void
    {
        $field = $this->sendFilter(requestMethod: RequestMethodEnum::GET, post: [], withCsrfTokenSource: false);

        $this->assertFalse($field->inputChecked);
    }
}
