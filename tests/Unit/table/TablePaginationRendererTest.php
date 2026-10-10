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
use actra\yuf\table\renderer\TablePaginationRenderer;
use actra\yuf\template\TemplateEngine;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\table\FixedPageDbResultTable;
use actra\yuf\tests\Double\template\TemplateEngineFactory;
use Override;
use PHPUnit\Framework\TestCase;

final class TablePaginationRendererTest extends TestCase
{
    private static int $tableCounter = 0;

    private TemplateEngine $templateEngine;

    #[Override]
    protected function setUp(): void
    {
        // The template cache checks for its files via is_dir()/file_exists(), which would report stale results
        clearstatcache();
        $this->templateEngine = TemplateEngineFactory::create(
            cacheDirectory: sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-pagination-test' . DIRECTORY_SEPARATOR,
            templateBaseDirectory: dirname(path: __DIR__, levels: 3) . '/',
        );
    }

    public function testDefaultTitlesAreEnglish(): void
    {
        $html = $this->render(renderer: new TablePaginationRenderer(individualHtmlSnippetPath: TablePaginationRendererTest::snippetPath()));

        $this->assertStringContainsString('<title>Previous</title>', $html);
        $this->assertStringContainsString('<title>Next</title>', $html);
    }

    public function testCustomTitlesAreRendered(): void
    {
        $html = $this->render(
            renderer: new TablePaginationRenderer(
                individualHtmlSnippetPath: TablePaginationRendererTest::snippetPath(),
                previousTitle: 'Zurück',
                nextTitle: 'Vor',
            ),
        );

        $this->assertStringContainsString('<title>Zurück</title>', $html);
        $this->assertStringContainsString('<title>Vor</title>', $html);
        $this->assertStringNotContainsString('Previous', $html);
    }

    public function testCustomTitlesAreEncoded(): void
    {
        $html = $this->render(
            renderer: new TablePaginationRenderer(
                individualHtmlSnippetPath: TablePaginationRendererTest::snippetPath(),
                previousTitle: '<b>Back</b>',
            ),
        );

        $this->assertStringContainsString('<title>&lt;b&gt;Back&lt;/b&gt;</title>', $html);
    }

    private static function snippetPath(): string
    {
        // Normalized, so the template cache can strip the base directory from it
        return dirname(path: __DIR__, levels: 3) . '/src/pagination/pagination.html';
    }

    private function render(TablePaginationRenderer $renderer): string
    {
        $table = new FixedPageDbResultTable(
            identifier: 'paginationTest' . ++TablePaginationRendererTest::$tableCounter,
            db: TablePaginationRendererTest::createStub(FrameworkDb::class),
            dbQuery: DbQuery::createFromSqlQuery(query: 'SELECT id FROM item'),
            templateEngine: $this->templateEngine,
            httpRequest: HttpRequestFactory::create(),
            session: new Session(storage: new ArraySessionStorage()),
            totalAmount: 100,
            currentPage: 2,
        );

        return $renderer->render(dbResultTable: $table, templateEngine: $this->templateEngine);
    }
}
