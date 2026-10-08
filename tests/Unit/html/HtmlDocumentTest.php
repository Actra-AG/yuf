<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\html;

use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlDocumentSettings;
use actra\yuf\security\CspNonce;
use actra\yuf\security\CsrfTokenSource;
use actra\yuf\tests\Double\security\CountingCsrfTokenSource;
use actra\yuf\tests\Double\security\InMemoryCsrfTokenSource;
use actra\yuf\tests\Double\template\TemplateEngineFactory;
use actra\yuf\tests\Double\template\TemplateWorkDirectory;
use OutOfBoundsException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HtmlDocumentTest extends TestCase
{
    private TemplateWorkDirectory $workDirectory;
    private string $viewDirectory;

    #[Override]
    protected function setUp(): void
    {
        clearstatcache();
        $this->workDirectory = new TemplateWorkDirectory();
        $this->viewDirectory = $this->workDirectory->templateDirectory . 'view/';
        mkdir(directory: $this->viewDirectory . 'html/group', recursive: true);
        mkdir(directory: $this->viewDirectory . 'templates', recursive: true);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->workDirectory->cleanUp();
    }

    private function writeContent(string $name, string $source): void
    {
        file_put_contents(filename: $this->viewDirectory . 'html/' . $name, data: $source);
    }

    private function writeTemplate(string $name, string $source): void
    {
        file_put_contents(filename: $this->viewDirectory . 'templates/' . $name . '.html', data: $source);
    }

    private function createDocument(
        string $fileTitle = 'page',
        ?string $fileGroup = null,
        ?string $fileName = 'page.html',
        string $languageCode = 'en',
        string $copyright = '2020-2026',
        string $robots = 'noindex, nofollow',
        ?CsrfTokenSource $csrfTokenSource = null,
    ): HtmlDocument {
        return new HtmlDocument(
            settings: new HtmlDocumentSettings(
                viewDirectory: $this->viewDirectory,
                fileGroup: $fileGroup,
                fileTitle: $fileTitle,
                fileName: $fileName,
                languageCode: $languageCode,
                copyright: $copyright,
                robots: $robots,
            ),
            cspNonce: new CspNonce(value: 'fixed+nonce=='),
            templateEngine: TemplateEngineFactory::create(
                cacheDirectory: $this->workDirectory->cacheDirectory,
                templateBaseDirectory: $this->workDirectory->templateDirectory,
            ),
            csrfTokenSource: $csrfTokenSource,
        );
    }

    public function testPathsAndNamesStartWithTheDefaults(): void
    {
        $document = $this->createDocument(fileTitle: 'start');

        $this->assertSame($this->viewDirectory . 'templates/', $document->templateDirectory);
        $this->assertSame($this->viewDirectory . 'html/', $document->contentFileDirectory);
        $this->assertSame('start.html', $document->contentFileName);
        $this->assertSame('default', $document->templateName);
    }

    public function testWithoutTemplateTheContentFileIsTheTemplate(): void
    {
        $this->writeContent(name: 'page.html', source: "<p>{tst:text value='bodyClassName'}</p>");

        $this->assertSame('<p>body-page</p>', $this->createDocument()->render());
    }

    public function testContentFileIsInsideTheTemplate(): void
    {
        $this->writeContent(name: 'page.html', source: '<p>content</p>');
        $this->writeTemplate(
            name: 'default',
            source: '<body class="{tst:text value=\'bodyClassName\'}"><tst:loadSubTpl tplfile="{this}"/></body>',
        );

        $this->assertSame('<body class="body-page"><p>content</p></body>', $this->createDocument()->render());
    }

    public function testDefaultReplacements(): void
    {
        $this->writeContent(
            name: 'page.html',
            source: "{tst:text value='language'}|{tst:text value='charset'}|{tst:text value='copyright'}|"
                . "{tst:text value='robots'}|[{tst:text value='scripts'}]|{tst:text value='cspNonce'}|"
                . "[{tst:text value='csrfField'}]|{tst:text value='requestedFileName'}",
        );

        $html = $this->createDocument(csrfTokenSource: new InMemoryCsrfTokenSource(token: 'tok<"'))->render();

        $this->assertSame(
            'en|UTF-8|2020-2026|noindex, nofollow|[]|fixed+nonce==|'
            . '[<input type="hidden" name="csrftoken" value="tok&lt;&quot;">]|page.html',
            $html,
        );
    }

    public function testWithoutCsrfTokenSourceTheFieldIsEmpty(): void
    {
        $this->writeContent(name: 'page.html', source: "[{tst:text value='csrfField'}]");

        $this->assertSame('[]', $this->createDocument()->render());
    }

    public function testTheCsrfTokenIsOnlyReadIfTheTemplateUsesTheField(): void
    {
        $csrfTokenSource = new CountingCsrfTokenSource();
        $this->writeContent(name: 'page.html', source: '<p>no form</p>');
        $document = $this->createDocument(csrfTokenSource: $csrfTokenSource);

        $this->assertSame('<p>no form</p>', $document->render());
        $this->assertSame(0, $csrfTokenSource->tokenReads);

        $this->writeContent(name: 'page.html', source: "[{tst:text value='csrfField'}]");
        $csrfTokenSource = new CountingCsrfTokenSource();
        $document = $this->createDocument(csrfTokenSource: $csrfTokenSource);

        $this->assertSame('[<input type="hidden" name="csrftoken" value="counted-token">]', $document->render());
        $this->assertSame(1, $csrfTokenSource->tokenReads);
    }

    public function testRequestValuesAreEscaped(): void
    {
        $this->writeContent(
            name: 'page.html',
            source: "<body class=\"{tst:text value='bodyClassName'}\""
                . " data-file=\"{tst:text value='requestedFileName'}\">",
        );

        $html = $this->createDocument(fileTitle: 'page', fileName: '"><script>x</script>.html')->render();

        $this->assertSame(
            '<body class="body-page" data-file="&quot;&gt;&lt;script&gt;x&lt;/script&gt;.html">',
            $html,
        );
    }

    public function testBodyClassNameIsEscaped(): void
    {
        $this->writeContent(name: 'page.html', source: "{tst:text value='bodyClassName'}");

        $html = $this->createDocumentWithTitle(fileTitle: 'a"b')->render();

        $this->assertSame('body-a&quot;b', $html);
    }

    private function createDocumentWithTitle(string $fileTitle): HtmlDocument
    {
        $document = $this->createDocument(fileTitle: $fileTitle);
        $document->contentFileName = 'page.html';

        return $document;
    }

    public function testMissingFileTitleWithoutLanguageGivesEmptyValues(): void
    {
        $this->writeContent(
            name: 'page.html',
            source: "[{tst:text value='language'}][{tst:text value='requestedFileName'}]",
        );

        $this->assertSame('[][]', $this->createDocument(languageCode: '', fileName: null)->render());
    }

    public function testViewAddsReplacements(): void
    {
        $this->writeContent(name: 'page.html', source: "{tst:text value='title'}|{tst:text value='raw'}");
        $document = $this->createDocument();
        $document->replacements->addText(identifier: 'title', text: '<Title>');
        $document->replacements->addHtml(identifier: 'raw', html: '<b>');

        $this->assertSame('&lt;Title&gt;|<b>', $document->render());
    }

    public function testOtherTemplateName(): void
    {
        $this->writeContent(name: 'page.html', source: 'content');
        $this->writeTemplate(name: 'default', source: 'default:<tst:loadSubTpl tplfile="{this}"/>');
        $this->writeTemplate(name: 'login', source: 'login:<tst:loadSubTpl tplfile="{this}"/>');
        $document = $this->createDocument();
        $document->templateName = 'login';

        $this->assertSame('login:content', $document->render());
    }

    public function testUnknownOrEmptyTemplateNameUsesTheContentFile(): void
    {
        $this->writeContent(name: 'page.html', source: 'content');
        $this->writeTemplate(name: 'default', source: 'default:<tst:loadSubTpl tplfile="{this}"/>');
        $unknown = $this->createDocument();
        $unknown->templateName = 'nope';
        $empty = $this->createDocument();
        $empty->templateName = '';

        $this->assertSame('content', $unknown->render());
        $this->assertSame('content', $empty->render());
    }

    public function testOtherContentFileName(): void
    {
        $this->writeContent(name: 'other.html', source: 'other');
        $document = $this->createDocument();
        $document->contentFileName = 'other.html';

        $this->assertSame('other', $document->render());
    }

    public function testOtherDirectories(): void
    {
        mkdir(directory: $this->workDirectory->templateDirectory . 'shared/templates', recursive: true);
        mkdir(directory: $this->workDirectory->templateDirectory . 'shared/html', recursive: true);
        file_put_contents(
            filename: $this->workDirectory->templateDirectory . 'shared/html/page.html',
            data: 'shared content',
        );
        file_put_contents(
            filename: $this->workDirectory->templateDirectory . 'shared/templates/default.html',
            data: 'shared:<tst:loadSubTpl tplfile="{this}"/>',
        );
        $document = $this->createDocument();
        $document->templateDirectory = $this->workDirectory->templateDirectory . 'shared/templates/';
        $document->contentFileDirectory = $this->workDirectory->templateDirectory . 'shared/html/';

        $this->assertSame('shared:shared content', $document->render());
    }

    public function testFileGroupIsASubDirectoryOfTheContent(): void
    {
        $this->writeContent(name: 'group/page.html', source: 'grouped');

        $this->assertSame('grouped', $this->createDocument(fileGroup: 'group')->render());
    }

    public function testMissingContentFileIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->createDocument()->render();
    }

    public function testEmptyContentFileNameIsNotFound(): void
    {
        $document = $this->createDocument();
        $document->contentFileName = '';

        $this->expectException(NotFoundException::class);
        $document->render();
    }

    public function testMissingTemplateAndContentFileIsNotFoundWithGroup(): void
    {
        $this->writeContent(name: 'page.html', source: 'x');

        $this->expectException(NotFoundException::class);
        $this->createDocument(fileGroup: 'other')->render();
    }

    /**
     * @return iterable<string, array{string|null, string}>
     */
    public static function leavingDirectoryProvider(): iterable
    {
        yield 'group parent directory' => ['..', 'page'];
        yield 'group with parent directory' => ['group/../..', 'page'];
        yield 'group absolute' => ['/etc', 'page'];
        yield 'group backslash' => ['group\\..', 'page'];
        yield 'group null byte' => ["group\0", 'page'];
        yield 'title with parent directory' => [null, '../secret'];
        yield 'title with nested parent directory' => [null, 'group/../../secret'];
        yield 'title parent directory only' => [null, '..'];
        yield 'title absolute' => [null, '/secret'];
    }

    #[DataProvider('leavingDirectoryProvider')]
    public function testRequestPathLeavingTheContentDirectoryIsNotFound(?string $fileGroup, string $fileTitle): void
    {
        // A file outside of the content directory that the traversal would reach
        file_put_contents(filename: $this->viewDirectory . 'secret.html', data: 'secret');
        file_put_contents(filename: $this->viewDirectory . 'html/secret.html', data: 'secret');
        $document = $this->createDocument(fileTitle: $fileTitle, fileGroup: $fileGroup);
        $document->contentFileName = 'secret.html';

        $this->expectException(NotFoundException::class);
        $document->render();
    }

    public function testNamesWithDotsAreNoTraversal(): void
    {
        $this->writeContent(name: 'group/a..b.html', source: 'dots');
        $document = $this->createDocument(fileTitle: 'a..b', fileGroup: 'group');

        $this->assertSame('dots', $document->render());
    }

    public function testActiveHtmlIds(): void
    {
        $document = $this->createDocument();

        $this->assertFalse($document->isActiveHtmlIdSet(key: 1));
        $this->assertSame([], $document->listActiveHtmlIds());

        $document->setActiveHtmlId(key: 1, val: 'users');
        $document->setActiveHtmlId(key: 2, val: 'list');
        $document->setActiveHtmlId(key: 1, val: 'groups');

        $this->assertTrue($document->isActiveHtmlIdSet(key: 1));
        $this->assertFalse($document->isActiveHtmlIdSet(key: 3));
        $this->assertSame('groups', $document->getActiveHtmlId(key: 1));
        $this->assertSame([1 => 'groups', 2 => 'list'], $document->listActiveHtmlIds());
    }

    public function testMissingActiveHtmlIdThrows(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessageIs('No active HTML id is set for key 3; check isActiveHtmlIdSet() first.');
        $this->createDocument()->getActiveHtmlId(key: 3);
    }

    public function testRequestedFileIsTheActiveIdWithoutOthers(): void
    {
        $this->writeContent(name: 'page.html', source: '<a id="nav-page">p</a><a id="nav-other">o</a>');
        $document = $this->createDocument();

        $html = $document->render();

        $this->assertSame('<a id="nav-page" class="active">p</a><a id="nav-other">o</a>', $html);
        $this->assertSame([1 => 'page'], $document->listActiveHtmlIds());
    }

    public function testRequestedFileWithGroupIsTheActiveId(): void
    {
        $this->writeContent(name: 'group/page.html', source: '<a id="nav-group-page">p</a><a id="nav-page">o</a>');
        $document = $this->createDocument(fileGroup: 'group');

        $html = $document->render();

        $this->assertSame('<a id="nav-group-page" class="active">p</a><a id="nav-page">o</a>', $html);
        $this->assertSame([1 => 'group-page'], $document->listActiveHtmlIds());
    }

    public function testExplicitActiveIdsReplaceTheDefault(): void
    {
        $this->writeContent(
            name: 'page.html',
            source: '<a id="nav-page">p</a><a id="nav-users">u</a><a id="nav-list">l</a>',
        );
        $document = $this->createDocument();
        $document->setActiveHtmlId(key: 1, val: 'users');
        $document->setActiveHtmlId(key: 2, val: 'list');

        $this->assertSame(
            '<a id="nav-page">p</a><a id="nav-users" class="active">u</a><a id="nav-list" class="active">l</a>',
            $document->render(),
        );
    }

    public function testActiveClassIsAppendedToExistingClasses(): void
    {
        $this->writeContent(
            name: 'page.html',
            source: '<li id="nav-page" class="item big">a</li>'
                . "<li\n  id=\"nav-page\"\n  class=\"x\">b</li>"
                . '<li id="nav-nope" class="item">c</li>',
        );

        $this->assertSame(
            '<li id="nav-page" class="item big active">a</li>'
            . "<li\n  id=\"nav-page\" class=\"x active\">b</li>"
            . '<li id="nav-nope" class="item">c</li>',
            $this->createDocument()->render(),
        );
    }

    public function testActiveIdMustMatchExactly(): void
    {
        $this->writeContent(name: 'page.html', source: '<a id="nav-pages">a</a><a id="nav-page-x">b</a>');

        $this->assertSame('<a id="nav-pages">a</a><a id="nav-page-x">b</a>', $this->createDocument()->render());
    }

    public function testActiveIdIsComparedAsText(): void
    {
        $this->writeContent(name: 'page.html', source: '<a id="nav-0">a</a>');
        $document = $this->createDocument();
        $document->setActiveHtmlId(key: 1, val: '0');

        $this->assertSame('<a id="nav-0" class="active">a</a>', $document->render());
    }

    public function testRenderingTwiceGivesTheSameResult(): void
    {
        $this->writeContent(name: 'page.html', source: '<a id="nav-page">p</a>');
        $document = $this->createDocument();

        $this->assertSame($document->render(), $document->render());
    }
}
