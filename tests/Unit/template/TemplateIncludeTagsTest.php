<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template;

use actra\yuf\tests\Double\template\TemplateCharacterizationTestCase;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use TypeError;

/**
 * Characterization of the template engine before the rewrite (docs/template-engine/plan.md, step 1): loadSubTpl and
 * snippet. The line break at the end of a fixture file is swallowed by the PHP closing tag of the compiled code,
 * unless the file is not a template (snippet with another extension than .html).
 */
final class TemplateIncludeTagsTest extends TemplateCharacterizationTestCase
{
    public function testLoadSubTplWithLiteralPath(): void
    {
        $html = $this->renderSource(
            source: 'x<tst:loadSubTpl tplfile="' . TemplateIncludeTagsTest::fixtureDirectory() . 'sub.html"/>y',
            data: ['name' => 'N'],
        );

        $this->assertSame('xsub:Ny', $html);
    }

    public function testLoadSubTplWithDataKeyInBraces(): void
    {
        $html = $this->renderSource(
            source: '[<tst:loadSubTpl tplfile="{this}"/>]',
            data: ['this' => TemplateIncludeTagsTest::fixtureDirectory() . 'sub.html', 'name' => 'N'],
        );

        $this->assertSame('[sub:N]', $html);
    }

    public function testLoadSubTplSeesTheLoopVariable(): void
    {
        $html = $this->renderSource(
            source: '<tst:for value="l" var="i"><tst:loadSubTpl tplfile="' . TemplateIncludeTagsTest::fixtureDirectory() . 'subLoop.html"/>;</tst:for>',
            data: ['l' => [1, 2]],
        );

        $this->assertSame('item:1;item:2;', $html);
    }

    public function testLoadSubTplWithMissingFileThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageIs('Could not find template file: /nonexistent/sub.html');

        $this->renderSource(source: '<tst:loadSubTpl tplfile="/nonexistent/sub.html"/>');
    }

    public function testLoadSubTplWithMissingDataKeyIsATypeError(): void
    {
        // Differs from docs/template-engine/design.md, which asks for a specific exception
        $this->expectException(TypeError::class);

        $this->renderSource(source: '<tst:loadSubTpl tplfile="{this}"/>');
    }

    public function testLoadSubTplCannotBeUsedInline(): void
    {
        $templateFile = $this->writeTemplate(source: "{tst:loadSubTpl tplfile='x.html'}");

        $this->expectException(Exception::class);
        $this->expectExceptionMessageIs(
            'Error while processing the template file ' . $templateFile . ': CustomTag "actra\yuf\template\customtags\LoadSubTplTag" is not allowed to use inline.',
        );

        $this->renderFile(templateFile: $templateFile);
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
        $html = $this->renderSource(source: $source, data: ['name' => 'N']);

        $this->assertSame($expected, $html);
    }

    public function testMissingHtmlSnippetThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageIs(
            'Could not find template file: ' . TemplateIncludeTagsTest::fixtureDirectory() . 'snippets/missing.html',
        );

        $this->renderSource(source: "{tst:snippet name='missing.html'}");
    }

    public function testSnippetNameCanLeaveTheSnippetsDirectory(): void
    {
        // Differs from docs/template-engine/design.md: a name with ".." is an error there
        $html = $this->renderSource(
            source: "{tst:snippet name='../sub.html'}",
            data: ['name' => 'N'],
        );

        $this->assertSame('sub:N', $html);
    }
}
