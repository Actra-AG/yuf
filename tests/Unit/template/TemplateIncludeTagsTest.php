<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template;

use actra\yuf\template\TemplateException;
use actra\yuf\tests\Double\template\TemplateEngineTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The `loadSubTpl` and `snippet` tags. The line break at the end of a fixture file is swallowed by the compiler,
 * unless the file is not a template (snippet with another extension than .html).
 */
final class TemplateIncludeTagsTest extends TemplateEngineTestCase
{
    public function testLoadSubTplWithLiteralPath(): void
    {
        $html = $this->render(
            source: 'x<tst:loadSubTpl tplfile="' . TemplateIncludeTagsTest::fixtureDirectory() . 'sub.html"/>y',
            data: ['name' => 'N'],
        );

        $this->assertSame('xsub:Ny', $html);
    }

    public function testLoadSubTplWithDataKeyInBraces(): void
    {
        $html = $this->render(
            source: '[<tst:loadSubTpl tplfile="{this}"/>]',
            data: ['this' => TemplateIncludeTagsTest::fixtureDirectory() . 'sub.html', 'name' => 'N'],
        );

        $this->assertSame('[sub:N]', $html);
    }

    public function testLoadSubTplSeesTheLoopVariable(): void
    {
        $html = $this->render(
            source: '<tst:for value="l" var="i"><tst:loadSubTpl tplfile="' . TemplateIncludeTagsTest::fixtureDirectory()
                . 'subLoop.html"/>;</tst:for>',
            data: ['l' => [1, 2]],
        );

        $this->assertSame('item:1;item:2;', $html);
    }

    public function testLoadSubTplWithMissingFileThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:loadSubTpl tplfile="/nonexistent/sub.html"/>');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'Template file not found: /nonexistent/sub.html in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile);
    }

    public function testLoadSubTplWithMissingDataKeyThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:loadSubTpl tplfile="{this}"/>');
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'The template data "this" does not exist. Check that the view provides a replacement with this '
                . 'identifier in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile);
    }

    public function testLoadSubTplInline(): void
    {
        $templateFile = $this->writeTemplate(
            source: "{tst:loadSubTpl tplfile='" . TemplateIncludeTagsTest::fixtureDirectory() . "sub.html'}",
        );

        $this->assertSame('sub:N', $this->renderFile(templateFile: $templateFile, data: ['name' => 'N']));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function snippetProvider(): iterable
    {
        yield 'html snippet inline' => ["A{tst:snippet name='hello.html'}B", 'A<b>snippet</b> NB'];
        yield 'html snippet element' => ['A<tst:snippet name="hello.html"/>B', 'A<b>snippet</b> NB'];
        // A file that is not .html is output as it is, without parsing it as a template
        yield 'svg snippet inline' => [
            "A{tst:snippet name='icon.svg'}B",
            "A<svg xmlns=\"http://www.w3.org/2000/svg\"><title>{tst:text value='name'}</title></svg>\nB",
        ];
        yield 'svg snippet element' => [
            'A<tst:snippet name="icon.svg"/>B',
            "A<svg xmlns=\"http://www.w3.org/2000/svg\"><title>{tst:text value='name'}</title></svg>\nB",
        ];
    }

    #[DataProvider('snippetProvider')]
    public function testSnippet(string $source, string $expected): void
    {
        $html = $this->render(source: $source, data: ['name' => 'N']);

        $this->assertSame($expected, $html);
    }

    public function testMissingHtmlSnippetThrows(): void
    {
        $templateFile = $this->writeTemplate(source: "{tst:snippet name='missing.html'}");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs('Snippet not found: missing.html in ' . $templateFile . ' on line 1');

        $this->renderFile(templateFile: $templateFile);
    }

    public function testSnippetNameLeavingTheSnippetsDirectory(): void
    {
        $templateFile = $this->writeTemplate(source: "{tst:snippet name='../sub.html'}");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'The snippet name "../sub.html" leaves the snippets directory in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile, data: ['name' => 'N']);
    }
}
