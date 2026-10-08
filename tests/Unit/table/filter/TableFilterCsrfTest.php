<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\table\filter;

use actra\yuf\core\HttpRequest;
use actra\yuf\db\DbQuery;
use actra\yuf\db\FrameworkDb;
use actra\yuf\security\CsrfToken;
use actra\yuf\table\filter\TableFilter;
use actra\yuf\tests\Double\table\FixedPageDbResultTable;
use actra\yuf\tests\Double\table\RecordingTableFilterField;
use actra\yuf\tests\Double\template\TemplateEngineFactory;
use Override;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class TableFilterCsrfTest extends TestCase
{
    private static int $instanceCounter = 0;
    /** @var array<mixed> */
    private array $savedSession = [];
    /** @var array<mixed> */
    private array $savedServer = [];

    #[Override]
    protected function setUp(): void
    {
        $this->savedSession = $_SESSION ?? [];
        $this->savedServer = $_SERVER;
        $_SESSION = [CsrfToken::CSRFTOKENSTORAGE => 'expected-token'];
    }

    #[Override]
    protected function tearDown(): void
    {
        $_SESSION = $this->savedSession;
        $_SERVER = $this->savedServer;
        $_GET = [];
        $_POST = [];
        $this->resetRequestInput();
    }

    private function resetRequestInput(): void
    {
        new ReflectionProperty(class: HttpRequest::class, property: 'inputData')->setValue(objectOrValue: null, value: null);
    }

    /**
     * @param array<string, string> $post
     * @param array<string, string> $query
     */
    private function sendFilter(string $requestMethod, array $post, array $query = []): RecordingTableFilterField
    {
        $identifier = 'csrfFilter' . ++TableFilterCsrfTest::$instanceCounter;
        $_SERVER['REQUEST_METHOD'] = $requestMethod;
        $_GET = [$identifier => ''] + $query;
        $_POST = $post;
        $this->resetRequestInput();
        $tableFilter = new TableFilter(identifier: $identifier);
        $field = new RecordingTableFilterField(parentFilter: $tableFilter);
        $tableFilter->addPrimaryField(abstractTableFilterField: $field);

        $tableFilter->validate(
            dbResultTable: new FixedPageDbResultTable(
                identifier: $identifier . 'Table',
                db: TableFilterCsrfTest::createStub(FrameworkDb::class),
                dbQuery: TableFilterCsrfTest::createStub(DbQuery::class),
                templateEngine: TemplateEngineFactory::create(
                    cacheDirectory: sys_get_temp_dir() . '/yuf-table-filter-test/',
                    templateBaseDirectory: sys_get_temp_dir() . '/',
                ),
                totalAmount: 0,
                currentPage: 1,
            ),
        );

        return $field;
    }

    public function testPostedFilterWithValidTokenIsApplied(): void
    {
        $field = $this->sendFilter(requestMethod: 'POST', post: ['csrftoken' => 'expected-token']);

        $this->assertTrue($field->inputChecked);
    }

    public function testPostedFilterWithWrongTokenIsIgnored(): void
    {
        $field = $this->sendFilter(requestMethod: 'POST', post: ['csrftoken' => 'wrong']);

        $this->assertFalse($field->inputChecked);
    }

    public function testPostedFilterWithoutTokenIsIgnored(): void
    {
        $field = $this->sendFilter(requestMethod: 'POST', post: []);

        $this->assertFalse($field->inputChecked);
    }

    public function testFilterWithTokenInTheUrlIsIgnored(): void
    {
        $field = $this->sendFilter(requestMethod: 'GET', post: [], query: ['csrftoken' => 'expected-token']);

        $this->assertFalse($field->inputChecked);
    }
}
