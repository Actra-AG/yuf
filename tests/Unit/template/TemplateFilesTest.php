<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template;

use actra\yuf\html\HtmlDataObject;
use actra\yuf\html\HtmlDataObjectCollection;
use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\tests\Double\template\TemplateCharacterizationTestCase;

/**
 * Characterization of the template engine before the rewrite (docs/template-engine/plan.md, step 1): the templates
 * that ship with yuf and the example. The whitespace of the expected output is the whitespace of today's engine:
 * every tag leaves the indentation of its line behind, and the whitespace between an if and its else is rendered
 * with the if branch. TablePaginationRendererTest checks the pagination titles through the real renderer.
 */
final class TemplateFilesTest extends TemplateCharacterizationTestCase
{
    /**
     * @param list<array{int, bool, bool, bool}> $pages number, is current page, group previous, group next
     */
    public static function createPaginationReplacements(
        array $pages,
        string $previousPageHref,
        string $nextPageHref,
    ): HtmlReplacementCollection {
        $collection = new HtmlDataObjectCollection();
        foreach ($pages as [$number, $isCurrentPage, $groupPreviousPages, $groupNextPages]) {
            $page = new HtmlDataObject();
            $page->addBooleanValue(propertyName: 'groupPreviousPages', booleanValue: $groupPreviousPages);
            $page->addBooleanValue(propertyName: 'isCurrentPage', booleanValue: $isCurrentPage);
            $page->addTextElement(propertyName: 'number', content: (string) $number, isEncodedForRendering: true);
            $page->addTextElement(
                propertyName: 'href',
                content: '?page=' . $number . '|list&amp;x=1',
                isEncodedForRendering: true,
            );
            $page->addBooleanValue(propertyName: 'groupNextPages', booleanValue: $groupNextPages);
            $collection->add(htmlDataObject: $page);
        }
        $replacements = new HtmlReplacementCollection();
        $replacements->addUnencodedText(identifier: 'previousTitle', content: 'Back <&>');
        $replacements->addEncodedText(identifier: 'previousPageHref', content: $previousPageHref);
        $replacements->addHtmlDataObjectCollection(identifier: 'pages', htmlDataObjectCollection: $collection);
        $replacements->addUnencodedText(identifier: 'nextTitle', content: 'Next');
        $replacements->addEncodedText(identifier: 'nextPageHref', content: $nextPageHref);

        return $replacements;
    }

    private static function createFilterField(
        string $identifier,
        bool $highlight,
        string $label,
        string $html,
    ): HtmlDataObject {
        $field = new HtmlDataObject();
        $field->addTextElement(propertyName: 'identifier', content: $identifier, isEncodedForRendering: true);
        $field->addBooleanValue(propertyName: 'highlight', booleanValue: $highlight);
        $field->addTextElement(propertyName: 'label', content: $label, isEncodedForRendering: true);
        $field->addTextElement(propertyName: 'html', content: $html, isEncodedForRendering: true);

        return $field;
    }

    public static function createTableFilterReplacements(
        bool $showLegend,
        bool $hasSecondaryFilters,
        bool $isSecondaryFilterTriggered,
    ): HtmlReplacementCollection {
        $replacements = new HtmlReplacementCollection();
        $replacements->addBool(identifier: 'showLegend', booleanValue: $showLegend);
        $replacements->addEncodedText(identifier: 'formAction', content: '?table&amp;find');
        $replacements->addEncodedText(
            identifier: 'csrfField',
            content: '<input type="hidden" name="csrf" value="token">',
        );
        $primaryFields = new HtmlDataObjectCollection();
        $primaryFields->add(
            htmlDataObject: TemplateFilesTest::createFilterField(
                identifier: 'name',
                highlight: false,
                label: 'Name',
                html: '<input name="name" type="text">',
            ),
        );
        $primaryFields->add(
            htmlDataObject: TemplateFilesTest::createFilterField(
                identifier: 'city',
                highlight: true,
                label: 'City',
                html: '<input name="city" type="text" value="Bern">',
            ),
        );
        $replacements->addHtmlDataObjectCollection(
            identifier: 'primaryFields',
            htmlDataObjectCollection: $primaryFields,
        );
        $replacements->addBool(identifier: 'hasSecondaryFilters', booleanValue: $hasSecondaryFilters);
        if ($hasSecondaryFilters) {
            $replacements->addBool(
                identifier: 'isSecondaryFilterTriggered',
                booleanValue: $isSecondaryFilterTriggered,
            );
            $secondaryFields = new HtmlDataObjectCollection();
            $secondaryFields->add(
                htmlDataObject: TemplateFilesTest::createFilterField(
                    identifier: 'status',
                    highlight: false,
                    label: 'Status',
                    html: '<select name="status"></select>',
                ),
            );
            $replacements->addHtmlDataObjectCollection(
                identifier: 'secondaryFields',
                htmlDataObjectCollection: $secondaryFields,
            );
        }
        $replacements->addEncodedText(identifier: 'resetHref', content: '?table&amp;reset');
        $replacements->addEncodedText(identifier: 'submitButtonLabel', content: 'Search');
        $replacements->addEncodedText(identifier: 'resetLinkLabel', content: 'Reset');

        return $replacements;
    }

    /**
     * @param list<string> $lines
     */
    private static function lines(array $lines): string
    {
        return implode(separator: "\n", array: $lines);
    }

    private function renderProjectFile(string $relativePath, HtmlReplacementCollection $replacements): string
    {
        return $this->renderFile(
            templateFile: TemplateFilesTest::projectDirectory() . $relativePath,
            data: $replacements,
        );
    }

    public function testPaginationOnTheFirstPage(): void
    {
        $replacements = TemplateFilesTest::createPaginationReplacements(
            pages: [[1, true, false, false], [2, false, false, false], [3, false, false, false]],
            previousPageHref: '',
            nextPageHref: '?page=2|list',
        );
        $html = $this->renderProjectFile(relativePath: 'src/pagination/pagination.html', replacements: $replacements);

        $this->assertSame(
            TemplateFilesTest::lines([
                '<div class="pagination">',
                '    <ul>',
                '                    <li class="backdisabled">',
                '        <span>',
                '          <svg height="24" role="img" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><title>Back &lt;&amp;&gt;</title><path',
                '                  d="M0 0h24v24H0z" fill="none"/><path',
                '                  d="M10.828 12l4.95 4.95-1.414 1.414L8 12l6.364-6.364 1.414 1.414z"/></svg>',
                '        </span>',
                '            </li>',
                '        ',
                '                                                        <li><strong>1</strong></li>',
                '            ',
                '                                                                        <li><a href="?page=2|list&amp;x=1">2</a></li>',
                '                                                                        <li><a href="?page=3|list&amp;x=1">3</a></li>',
                '                                                    <li class="next">',
                '                <a href="?page=2|list">',
                '                    <svg height="24" role="img" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg">',
                '                        <title>Next</title>',
                '                        <path d="M0 0h24v24H0z" fill="none"/>',
                '                        <path d="M13.172 12l-4.95-4.95 1.414-1.414L16 12l-6.364 6.364-1.414-1.414z"/>',
                '                    </svg>',
                '                </a>',
                '            </li>',
                '            </ul>',
                '</div>',
            ]),
            $html,
        );
    }

    public function testPaginationInTheMiddleWithGroupDots(): void
    {
        $replacements = TemplateFilesTest::createPaginationReplacements(
            pages: [
                [1, false, false, false],
                [2, false, false, true],
                [4, true, false, false],
                [6, false, true, false],
                [7, false, false, false],
            ],
            previousPageHref: '?page=3|list',
            nextPageHref: '?page=5|list',
        );
        $html = $this->renderProjectFile(relativePath: 'src/pagination/pagination.html', replacements: $replacements);

        $this->assertSame(
            TemplateFilesTest::lines([
                '<div class="pagination">',
                '    <ul>',
                '                    <li class="back">',
                '                <a href="?page=3|list">',
                '                    <svg height="24" role="img" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg">',
                '                        <title>Back &lt;&amp;&gt;</title>',
                '                        <path d="M0 0h24v24H0z" fill="none"/>',
                '                        <path d="M10.828 12l4.95 4.95-1.414 1.414L8 12l6.364-6.364 1.414 1.414z"/>',
                '                    </svg>',
                '                </a>',
                '            </li>',
                '                                                        <li><a href="?page=1|list&amp;x=1">1</a></li>',
                '                                                                        <li><a href="?page=2|list&amp;x=1">2</a></li>',
                '                                        <li><span class="pagination-dots">...</span></li>',
                '                                                            <li><strong>4</strong></li>',
                '            ',
                '                                                            <li><span class="pagination-dots">...</span></li>',
                '                                        <li><a href="?page=6|list&amp;x=1">6</a></li>',
                '                                                                        <li><a href="?page=7|list&amp;x=1">7</a></li>',
                '                                                    <li class="next">',
                '                <a href="?page=5|list">',
                '                    <svg height="24" role="img" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg">',
                '                        <title>Next</title>',
                '                        <path d="M0 0h24v24H0z" fill="none"/>',
                '                        <path d="M13.172 12l-4.95-4.95 1.414-1.414L16 12l-6.364 6.364-1.414-1.414z"/>',
                '                    </svg>',
                '                </a>',
                '            </li>',
                '            </ul>',
                '</div>',
            ]),
            $html,
        );
    }

    public function testPaginationOnTheLastPage(): void
    {
        $replacements = TemplateFilesTest::createPaginationReplacements(
            pages: [[2, false, false, false], [3, true, false, false]],
            previousPageHref: '?page=2|list',
            nextPageHref: '',
        );
        $html = $this->renderProjectFile(relativePath: 'src/pagination/pagination.html', replacements: $replacements);

        $this->assertSame(
            TemplateFilesTest::lines([
                '<div class="pagination">',
                '    <ul>',
                '                    <li class="back">',
                '                <a href="?page=2|list">',
                '                    <svg height="24" role="img" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg">',
                '                        <title>Back &lt;&amp;&gt;</title>',
                '                        <path d="M0 0h24v24H0z" fill="none"/>',
                '                        <path d="M10.828 12l4.95 4.95-1.414 1.414L8 12l6.364-6.364 1.414 1.414z"/>',
                '                    </svg>',
                '                </a>',
                '            </li>',
                '                                                        <li><a href="?page=2|list&amp;x=1">2</a></li>',
                '                                                                        <li><strong>3</strong></li>',
                '            ',
                '                                                    <li class="nextdisabled">',
                '        <span>',
                '          <svg height="24" role="img" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><title>Next</title><path',
                '                  d="M0 0h24v24H0z" fill="none"/><path',
                '                  d="M13.172 12l-4.95-4.95 1.414-1.414L16 12l-6.364 6.364-1.414-1.414z"/></svg>',
                '        </span>',
                '            </li>',
                '        ',
                '            </ul>',
                '</div>',
            ]),
            $html,
        );
    }

    public function testTableFilterWithLegendAndTriggeredSecondaryFilters(): void
    {
        $replacements = TemplateFilesTest::createTableFilterReplacements(
            showLegend: true,
            hasSecondaryFilters: true,
            isSecondaryFilterTriggered: true,
        );
        $html = $this->renderProjectFile(
            relativePath: 'src/table/filter/tableFilter.html',
            replacements: $replacements,
        );

        $this->assertSame(
            TemplateFilesTest::lines([
                '<div class="table-filter-wrapper">',
                '            <div class="table-filter-legend-wrap">',
                '            <button class="trigger-table-filter-legend">',
                '                <svg height="24" role="img" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg">',
                '                    <title>Suchoptionen</title>',
                '                    <path d="M0 0h24v24H0z" fill="none"/>',
                '                    <path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm0-2a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm-1-5h2v2h-2v-2zm2-1.645V14h-2v-1.5a1 1 0 0 1 1-1 1.5 1.5 0 1 0-1.471-1.794l-1.962-.393A3.501 3.501 0 1 1 13 13.355z"/>',
                '                </svg>',
                '            </button>',
                '            <div class="table-filter-legend">',
                '                <dl>',
                '                    <dt>.</dt>',
                '                    <dd>Feld ist nicht leer</dd>',
                '                </dl>',
                '                <dl>',
                '                    <dt>!term</dt>',
                '                    <dd>Feld enthält "term" nicht</dd>',
                '                </dl>',
                '                <dl>',
                '                    <dt>+term</dt>',
                '                    <dd>Feld muss "term" enthalten</dd>',
                '                </dl>',
                '                <dl>',
                '                    <dt>_</dt>',
                '                    <dd>Feld ist leer</dd>',
                '                </dl>',
                '                <dl>',
                '                    <dt>%</dt>',
                '                    <dd>Wildcard</dd>',
                '                </dl>',
                '                <dl>',
                '                    <dt>*</dt>',
                '                    <dd>Wildcard</dd>',
                '                </dl>',
                '                <dl>',
                '                    <dt>"term1 term2"</dt>',
                '                    <dd>Exakte Übereinstimmung</dd>',
                '                </dl>',
                '            </div>',
                '        </div>',
                '        <form action="?table&amp;find" class="form-tablefilter" method="post">',
                '        <input type="hidden" name="csrf" value="token">        <div class="table-filter-primary-wrap">',
                '            <ul class="table-filter table-filter-primary">',
                '                                    <li>',
                '                                                    <label>',
                '                                Name                                <input name="name" type="text">                            </label>',
                '                                            </li>',
                '                                    <li>',
                '                                                    <label class="highlight">',
                '                                City                                <input name="city" type="text" value="Bern">                            </label>',
                '                        ',
                '                                            </li>',
                '                            </ul>',
                '                                                <button class="trigger-table-filter-secondary triggered" type="button">',
                '                        <svg height="24" role="img" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg">',
                '                            <title>Erweiterte Suche</title>',
                '                            <path d="M0 0h24v24H0z" fill="none"/>',
                '                            <path d="M11 11V7h2v4h4v2h-4v4h-2v-4H7v-2h4zm1 11C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm0-2a8 8 0 1 0 0-16 8 8 0 0 0 0 16z"/>',
                '                        </svg>',
                '                    </button>',
                '                ',
                '                                    </div>',
                '                    <div class="table-filter-wrap">',
                '                <ul class="table-filter table-filter-secondary">',
                '                                            <li>',
                '                                                            <label>',
                '                                    Status                                    <select name="status"></select>                                </label>',
                '                                                    </li>',
                '                                    </ul>',
                '            </div>',
                '                <div class="form-control">',
                '            <button type="submit">Search</button>',
                '            <a class="reset-filter" href="?table&amp;reset">Reset</a>',
                '        </div>',
                '    </form>',
                '</div>',
            ]),
            $html,
        );
    }

    public function testTableFilterWithUntriggeredSecondaryFilters(): void
    {
        $replacements = TemplateFilesTest::createTableFilterReplacements(
            showLegend: false,
            hasSecondaryFilters: true,
            isSecondaryFilterTriggered: false,
        );
        $html = $this->renderProjectFile(
            relativePath: 'src/table/filter/tableFilter.html',
            replacements: $replacements,
        );

        $this->assertSame(
            TemplateFilesTest::lines([
                '<div class="table-filter-wrapper">',
                '        <form action="?table&amp;find" class="form-tablefilter" method="post">',
                '        <input type="hidden" name="csrf" value="token">        <div class="table-filter-primary-wrap">',
                '            <ul class="table-filter table-filter-primary">',
                '                                    <li>',
                '                                                    <label>',
                '                                Name                                <input name="name" type="text">                            </label>',
                '                                            </li>',
                '                                    <li>',
                '                                                    <label class="highlight">',
                '                                City                                <input name="city" type="text" value="Bern">                            </label>',
                '                        ',
                '                                            </li>',
                '                            </ul>',
                '                                                <button class="trigger-table-filter-secondary" type="button">',
                '                        <svg height="24" role="img" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg">',
                '                            <title>Erweiterte Suche</title>',
                '                            <path d="M0 0h24v24H0z" fill="none"/>',
                '                            <path d="M11 11V7h2v4h4v2h-4v4h-2v-4H7v-2h4zm1 11C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm0-2a8 8 0 1 0 0-16 8 8 0 0 0 0 16z"/>',
                '                        </svg>',
                '                    </button>',
                '                                    </div>',
                '                    <div class="table-filter-wrap">',
                '                <ul class="table-filter table-filter-secondary">',
                '                                            <li>',
                '                                                            <label>',
                '                                    Status                                    <select name="status"></select>                                </label>',
                '                                                    </li>',
                '                                    </ul>',
                '            </div>',
                '                <div class="form-control">',
                '            <button type="submit">Search</button>',
                '            <a class="reset-filter" href="?table&amp;reset">Reset</a>',
                '        </div>',
                '    </form>',
                '</div>',
            ]),
            $html,
        );
    }

    public function testTableFilterWithoutSecondaryFilters(): void
    {
        $replacements = TemplateFilesTest::createTableFilterReplacements(
            showLegend: false,
            hasSecondaryFilters: false,
            isSecondaryFilterTriggered: false,
        );
        $html = $this->renderProjectFile(
            relativePath: 'src/table/filter/tableFilter.html',
            replacements: $replacements,
        );

        $this->assertSame(
            TemplateFilesTest::lines([
                '<div class="table-filter-wrapper">',
                '        <form action="?table&amp;find" class="form-tablefilter" method="post">',
                '        <input type="hidden" name="csrf" value="token">        <div class="table-filter-primary-wrap">',
                '            <ul class="table-filter table-filter-primary">',
                '                                    <li>',
                '                                                    <label>',
                '                                Name                                <input name="name" type="text">                            </label>',
                '                                            </li>',
                '                                    <li>',
                '                                                    <label class="highlight">',
                '                                City                                <input name="city" type="text" value="Bern">                            </label>',
                '                        ',
                '                                            </li>',
                '                            </ul>',
                '                    </div>',
                '                <div class="form-control">',
                '            <button type="submit">Search</button>',
                '            <a class="reset-filter" href="?table&amp;reset">Reset</a>',
                '        </div>',
                '    </form>',
                '</div>',
            ]),
            $html,
        );
    }

    public function testExampleTemplateWithContentFile(): void
    {
        $replacements = new HtmlReplacementCollection();
        // The keys of HtmlDocument and of the example IndexView
        foreach (
            [
                'bodyClassName' => 'body-index',
                'language' => 'en',
                'charset' => 'UTF-8',
                'copyright' => '2026 Example',
                'robots' => 'index,follow',
                'title' => 'Hello World',
                'greeting' => 'Hello World!',
                'this' => TemplateFilesTest::projectDirectory() . 'example/app/view/frontend/html/index.html',
            ] as $identifier => $content
        ) {
            $replacements->addEncodedText(identifier: $identifier, content: $content);
        }
        $html = $this->renderProjectFile(
            relativePath: 'example/app/view/frontend/templates/default.html',
            replacements: $replacements,
        );

        $this->assertSame(
            TemplateFilesTest::lines([
                '<!DOCTYPE html>',
                '<html lang="en">',
                '<head>',
                '    <meta charset="UTF-8">',
                '    <meta name="viewport" content="width=device-width, initial-scale=1">',
                '    <meta name="robots" content="index,follow">',
                '    <title>Hello World</title>',
                '</head>',
                '<body class="body-index">',
                '<main>',
                '    <h1>Hello World!</h1>',
                '<p>This page is rendered by yuf.</p></main>',
                '<footer>&copy; 2026 Example</footer>',
                '</body>',
                '</html>',
            ]),
            $html,
        );
    }

    public function testNotFoundErrorPage(): void
    {
        $replacements = new HtmlReplacementCollection();
        // The keys of the ExceptionHandler
        foreach (
            [
                'bodyClassName' => 'body-notFound',
                'language' => 'en',
                'charset' => 'UTF-8',
                'robots' => 'noindex,nofollow',
            ] as $identifier => $content
        ) {
            $replacements->addEncodedText(identifier: $identifier, content: $content);
        }
        $html = $this->renderProjectFile(relativePath: 'example/app/error_docs/notFound.html', replacements: $replacements);

        $this->assertSame(
            TemplateFilesTest::lines([
                '<!DOCTYPE html>',
                '<html lang="en">',
                '<head>',
                '    <meta charset="UTF-8">',
                '    <meta name="robots" content="noindex,nofollow">',
                '    <title>Page not found</title>',
                '</head>',
                '<body class="body-notFound">',
                '<h1>Page not found</h1>',
                '</body>',
                '</html>',
            ]),
            $html,
        );
    }

    public function testDefaultErrorPage(): void
    {
        $replacements = new HtmlReplacementCollection();
        // The keys of the ExceptionHandler
        foreach (
            [
                'bodyClassName' => 'body-default',
                'language' => 'en',
                'charset' => 'UTF-8',
                'robots' => 'noindex,nofollow',
            ] as $identifier => $content
        ) {
            $replacements->addEncodedText(identifier: $identifier, content: $content);
        }
        $html = $this->renderProjectFile(relativePath: 'example/app/error_docs/default.html', replacements: $replacements);

        $this->assertSame(
            TemplateFilesTest::lines([
                '<!DOCTYPE html>',
                '<html lang="en">',
                '<head>',
                '    <meta charset="UTF-8">',
                '    <meta name="robots" content="noindex,nofollow">',
                '    <title>Internal Server Error</title>',
                '</head>',
                '<body class="body-default">',
                '<h1>Internal Server Error</h1>',
                '</body>',
                '</html>',
            ]),
            $html,
        );
    }
}
