<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\table;

use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\db\DbRowValueException;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\table\column\ActionsColumn;
use actra\yuf\table\column\CallbackColumn;
use actra\yuf\table\table\DbResultTable;
use actra\yuf\table\TableHelper;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\core\RecordingResponseSender;
use actra\yuf\tests\Double\core\ResponseSentException;
use actra\yuf\tests\Double\db\SqliteDatabase;
use actra\yuf\tests\Double\template\TemplateEngineFactory;
use PHPUnit\Framework\TestCase;

/**
 * `DbResultTable::exportCsv()` on a SQLite database (users: Anna 30, Ben without age, Cleo 41).
 */
final class DbResultTableCsvExportTest extends TestCase
{
    private const string BOM = "\xEF\xBB\xBF";

    /**
     * @param array<string, string> $query
     */
    private function createTable(array $query = [], string $sql = 'SELECT id, name, age FROM users'): DbResultTable
    {
        $table = TableHelper::createDbResultTable(
            identifier: 'users',
            db: SqliteDatabase::create(),
            selectQuery: $sql,
            templateEngine: TemplateEngineFactory::create(
                cacheDirectory: sys_get_temp_dir() . '/yuf-db-result-table-csv-test/',
                templateBaseDirectory: dirname(path: __DIR__, levels: 3) . '/',
            ),
            httpRequest: HttpRequestFactory::create(queryParameters: $query),
            session: new Session(storage: new ArraySessionStorage()),
            itemsPerPage: 2,
        );
        $table->addColumn(
            abstractTableColumn: TableHelper::createDefaultColumn(identifier: 'id', label: 'Id', isSortable: true),
            isDefaultSortColumn: true,
        );
        $table->addColumn(
            abstractTableColumn: TableHelper::createDefaultColumn(
                identifier: 'name',
                label: 'Name &amp; <b>Title</b>',
                isSortable: true,
            ),
        );
        $table->addColumn(abstractTableColumn: TableHelper::createDefaultColumn(identifier: 'age', label: 'Age'));

        return $table;
    }

    /**
     * Runs the export with a recording sender; returns the content of the download and removes its file.
     */
    private function export(DbResultTable $table, RecordingResponseSender $sender): string
    {
        try {
            $table->exportCsv(fileName: 'users.csv', responseSender: $sender);
        } catch (ResponseSentException) {
            // The double throws instead of ending the process
        }
        $response = $sender->sentResponse;
        $this->assertNotNull($response);
        $this->assertSame(HttpStatusCodeEnum::HTTP_OK, $response->httpStatusCode);
        $this->assertSame('attachment; filename="users.csv"', $response->getHeader(key: 'Content-Disposition'));
        $path = $response->getContentFilePath();
        $this->assertNotNull($path);
        $content = file_get_contents(filename: $path);
        $this->assertIsString($content);
        $sender->runAfterResponseCallbacks();
        $this->assertFileDoesNotExist($path);

        return $content;
    }

    public function testExportsLabelsAndAllRowsNotOnlyTheCurrentPage(): void
    {
        $content = $this->export(table: $this->createTable(), sender: new RecordingResponseSender());

        // 3 rows, 2 per page; NULL is an empty cell, so Ben's age does not shift; the label is plain text
        $this->assertSame(
            DbResultTableCsvExportTest::BOM . "Id;\"Name & Title\";Age\n1;Anna;30\n2;Ben;\n3;Cleo;41\n",
            $content,
        );
    }

    public function testRespectsTheSortingOfTheUser(): void
    {
        $content = $this->export(
            table: $this->createTable(query: ['sort' => 'users|name|DESC']),
            sender: new RecordingResponseSender(),
        );

        $this->assertSame(
            DbResultTableCsvExportTest::BOM . "Id;\"Name & Title\";Age\n3;Cleo;41\n2;Ben;\n1;Anna;30\n",
            $content,
        );
    }

    public function testRespectsTheWhereOfTheQuery(): void
    {
        $content = $this->export(
            table: $this->createTable(sql: 'SELECT id, name, age FROM users WHERE age IS NOT NULL'),
            sender: new RecordingResponseSender(),
        );

        $this->assertSame(
            DbResultTableCsvExportTest::BOM . "Id;\"Name & Title\";Age\n1;Anna;30\n3;Cleo;41\n",
            $content,
        );
    }

    public function testExportIgnoresThePageOfTheRequestAndKeepsTheStoredPage(): void
    {
        $table = $this->createTable(query: ['page' => '2|users']);

        $content = $this->export(table: $table, sender: new RecordingResponseSender());

        $this->assertStringContainsString("1;Anna;30\n2;Ben;\n3;Cleo;41\n", $content);
        $this->assertSame(1, $table->getCurrentPaginationPage());
    }

    public function testColumnsWithoutDataOfTheirOwnAreNotExported(): void
    {
        $table = $this->createTable();
        $table->addColumn(abstractTableColumn: new ActionsColumn(identifier: 'actions'));
        $table->addColumn(
            abstractTableColumn: new CallbackColumn(
                identifier: 'computed',
                label: 'Computed',
                callbackFunction: static fn(): string => 'x',
            ),
        );

        $content = $this->export(table: $table, sender: new RecordingResponseSender());

        $this->assertStringStartsWith(
            DbResultTableCsvExportTest::BOM . "Id;\"Name & Title\";Age\n1;Anna;30\n",
            $content,
        );
    }

    public function testFormulaInTheDataIsProtected(): void
    {
        $content = $this->export(
            table: $this->createTable(sql: "SELECT id, '=1+1' AS name, age FROM users WHERE id = 1"),
            sender: new RecordingResponseSender(),
        );

        $this->assertStringContainsString("1;'=1+1;30\n", $content);
    }

    public function testExportAfterRenderingKeepsTheSortingOnce(): void
    {
        $table = $this->createTable(query: ['sort' => 'users|name|DESC']);
        $table->render();

        $content = $this->export(table: $table, sender: new RecordingResponseSender());

        $this->assertStringContainsString("3;Cleo;41\n2;Ben;\n1;Anna;30\n", $content);
    }

    public function testMissingColumnInTheQueryThrows(): void
    {
        $table = $this->createTable(sql: 'SELECT id, name FROM users');

        $this->expectExceptionObject(new DbRowValueException(message: 'Column "age" does not exist in the row.'));
        $table->exportCsv(fileName: 'users.csv', responseSender: new RecordingResponseSender());
    }
}
