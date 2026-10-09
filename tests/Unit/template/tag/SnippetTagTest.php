<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template\tag;

use actra\yuf\template\cache\DirectoryTemplateCache;
use actra\yuf\template\tag\SnippetTag;
use actra\yuf\template\tag\TemplateTagCollection;
use actra\yuf\template\tag\TextTag;
use actra\yuf\template\TemplateData;
use actra\yuf\template\TemplateEngine;
use actra\yuf\template\TemplateException;
use actra\yuf\tests\Double\template\TemplateWorkDirectory;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SnippetTagTest extends TestCase
{
    private TemplateWorkDirectory $workDirectory;
    private string $snippetsDirectory;
    private TemplateEngine $engine;

    #[Override]
    protected function setUp(): void
    {
        $this->workDirectory = new TemplateWorkDirectory();
        $this->snippetsDirectory = $this->workDirectory->templateDirectory . 'snippets/';
        mkdir(directory: $this->snippetsDirectory . 'sub', recursive: true);
        file_put_contents(filename: $this->snippetsDirectory . 'page.html', data: "<b>{tst:text value='name'}</b>");
        file_put_contents(filename: $this->snippetsDirectory . 'UPPER.HTML', data: "{tst:text value='name'}");
        file_put_contents(filename: $this->snippetsDirectory . 'sub/deep.html', data: 'deep');
        file_put_contents(
            filename: $this->snippetsDirectory . 'icon.svg',
            data: "<svg>{tst:text value='name'}</svg>\n",
        );
        file_put_contents(filename: $this->workDirectory->templateDirectory . 'secret.html', data: 'secret');
        $this->engine = new TemplateEngine(
            cache: new DirectoryTemplateCache(
                cacheDirectory: $this->workDirectory->cacheDirectory,
                templateBaseDirectory: $this->workDirectory->templateDirectory,
            ),
            tags: new TemplateTagCollection(new TextTag(), new SnippetTag(snippetsDirectory: $this->snippetsDirectory)),
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->workDirectory->cleanUp();
    }

    private function render(string $source): string
    {
        return $this->engine->render(
            templateFile: $this->workDirectory->writeTemplate(source: $source),
            data: new TemplateData(values: ['name' => '<N>']),
        );
    }

    public function testHtmlSnippetIsRenderedAsATemplateWithTheSameData(): void
    {
        $this->assertSame('a<b>&lt;N&gt;</b>b', $this->render(source: "a{tst:snippet name='page.html'}b"));
        $this->assertSame('a<b>&lt;N&gt;</b>b', $this->render(source: 'a<tst:snippet name="page.html"/>b'));
    }

    public function testHtmlExtensionIsCaseInsensitive(): void
    {
        $this->assertSame('&lt;N&gt;', $this->render(source: "{tst:snippet name='UPPER.HTML'}"));
    }

    public function testOtherFilesAreOutputAsTheyAre(): void
    {
        $this->assertSame(
            "a<svg>{tst:text value='name'}</svg>\nb",
            $this->render(source: "a{tst:snippet name='icon.svg'}b"),
        );
    }

    public function testSnippetInASubdirectory(): void
    {
        $this->assertSame('deep', $this->render(source: "{tst:snippet name='sub/deep.html'}"));
    }

    public function testSnippetsDirectoryWithoutTrailingSlash(): void
    {
        $engine = new TemplateEngine(
            cache: new DirectoryTemplateCache(
                cacheDirectory: $this->workDirectory->cacheDirectory,
                templateBaseDirectory: $this->workDirectory->templateDirectory,
            ),
            tags: new TemplateTagCollection(
                new SnippetTag(snippetsDirectory: rtrim(string: $this->snippetsDirectory, characters: '/')),
            ),
        );

        $html = $engine->render(
            templateFile: $this->workDirectory->writeTemplate(source: "{tst:snippet name='sub/deep.html'}"),
            data: new TemplateData(),
        );

        $this->assertSame('deep', $html);
    }

    public function testEmptyNameRendersNothing(): void
    {
        $this->assertSame('ab', $this->render(source: "a{tst:snippet name=''}b"));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function leavingNameProvider(): iterable
    {
        yield 'parent directory' => ['../secret.html'];
        yield 'parent directory in the middle' => ['sub/../../secret.html'];
        yield 'only parent directory' => ['..'];
        yield 'absolute path' => ['/etc/hostname'];
        yield 'backslash separator' => ['..\\secret.html'];
        yield 'null byte' => ["page.html\0../secret.html"];
    }

    #[DataProvider('leavingNameProvider')]
    public function testNameThatLeavesTheSnippetsDirectoryThrows(string $name): void
    {
        $templateFile = $this->workDirectory->writeTemplate(source: '{tst:snippet name=\'' . $name . '\'}');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'The snippet name "' . $name . '" leaves the snippets directory in ' . $templateFile . ' on line 1',
        );

        $this->engine->render(templateFile: $templateFile, data: new TemplateData());
    }

    public function testSymbolicLinkThatLeavesTheSnippetsDirectoryThrows(): void
    {
        symlink(
            target: $this->workDirectory->templateDirectory . 'secret.html',
            link: $this->snippetsDirectory . 'link.html',
        );
        $templateFile = $this->workDirectory->writeTemplate(source: "{tst:snippet name='link.html'}");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'The snippet name "link.html" leaves the snippets directory in ' . $templateFile . ' on line 1',
        );

        $this->engine->render(templateFile: $templateFile, data: new TemplateData());
    }

    public function testMissingSnippetThrows(): void
    {
        $templateFile = $this->workDirectory->writeTemplate(source: "{tst:snippet name='missing.svg'}");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs('Snippet not found: missing.svg in ' . $templateFile . ' on line 1');

        $this->engine->render(templateFile: $templateFile, data: new TemplateData());
    }

    public function testDirectoryIsNoSnippet(): void
    {
        $templateFile = $this->workDirectory->writeTemplate(source: "{tst:snippet name='sub'}");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs('Snippet not found: sub in ' . $templateFile . ' on line 1');

        $this->engine->render(templateFile: $templateFile, data: new TemplateData());
    }

    public function testMissingNameAttributeThrows(): void
    {
        $templateFile = $this->workDirectory->writeTemplate(source: '<tst:snippet/>');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs('Missing attribute "name" in ' . $templateFile . ' on line 1');

        $this->engine->render(templateFile: $templateFile, data: new TemplateData());
    }

    public function testSnippetThatIsNoTemplateIsReadOncePerTag(): void
    {
        $first = $this->render(source: "{tst:snippet name='icon.svg'}");
        file_put_contents(filename: $this->snippetsDirectory . 'icon.svg', data: 'changed');

        $this->assertSame($first, $this->render(source: "{tst:snippet name='icon.svg'}"));
    }
}
