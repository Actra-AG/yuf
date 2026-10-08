<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template;

use actra\yuf\template\TemplateException;
use actra\yuf\tests\Double\template\TemplateCharacterizationTestCase;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use TypeError;

/**
 * Characterization of the template engine before the rewrite (docs/template-engine/plan.md, step 1): loadSubTpl and
 * snippet. The line break at the end of a fixture file is swallowed by the PHP closing tag of the compiled code,
 * unless the file is not a template (snippet with another extension than .html).
 */
abstract class AbstractTemplateIncludeTagsTestCase extends TemplateCharacterizationTestCase
{
    public function testLoadSubTplWithLiteralPath(): void
    {
        $html = $this->renderSource(
            source: 'x<tst:loadSubTpl tplfile="' . AbstractTemplateIncludeTagsTestCase::fixtureDirectory() . 'sub.html"/>y',
            data: ['name' => 'N'],
        );

        $this->assertSame('xsub:Ny', $html);
    }

    public function testLoadSubTplWithDataKeyInBraces(): void
    {
        $html = $this->renderSource(
            source: '[<tst:loadSubTpl tplfile="{this}"/>]',
            data: ['this' => AbstractTemplateIncludeTagsTestCase::fixtureDirectory() . 'sub.html', 'name' => 'N'],
        );

        $this->assertSame('[sub:N]', $html);
    }

    public function testLoadSubTplSeesTheLoopVariable(): void
    {
        $html = $this->renderSource(
            source: '<tst:for value="l" var="i"><tst:loadSubTpl tplfile="' . AbstractTemplateIncludeTagsTestCase::fixtureDirectory() . 'subLoop.html"/>;</tst:for>',
            data: ['l' => [1, 2]],
        );

        $this->assertSame('item:1;item:2;', $html);
    }

    public function testLoadSubTplWithMissingFileThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:loadSubTpl tplfile="/nonexistent/sub.html"/>');

        $this->expectEngineException(
            oldClass: Exception::class,
            oldMessage: 'Could not find template file: /nonexistent/sub.html',
            newMessage: 'Template file not found: /nonexistent/sub.html in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile);
    }

    public function testLoadSubTplWithMissingDataKeyThrows(): void
    {
        // Differs: the old engine fails with a TypeError, the new engine with a TemplateException
        $templateFile = $this->writeTemplate(source: '<tst:loadSubTpl tplfile="{this}"/>');
        if ($this->isNewEngine()) {
            $this->expectException(TemplateException::class);
            $this->expectExceptionMessageIs(
                'The template data "this" does not exist. Check that the view provides a replacement with this identifier in ' . $templateFile . ' on line 1',
            );
        } else {
            $this->expectException(TypeError::class);
        }

        $this->renderFile(templateFile: $templateFile);
    }

    public function testLoadSubTplInline(): void
    {
        // Differs: the old engine does not allow loadSubTpl as an inline tag, the new engine does
        $templateFile = $this->writeTemplate(
            source: "{tst:loadSubTpl tplfile='" . AbstractTemplateIncludeTagsTestCase::fixtureDirectory() . "sub.html'}",
        );
        if (!$this->isNewEngine()) {
            $this->expectException(Exception::class);
            $this->expectExceptionMessageIs(
                'Error while processing the template file ' . $templateFile . ': CustomTag "actra\yuf\template\customtags\LoadSubTplTag" is not allowed to use inline.',
            );
        }

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
        $html = $this->renderSource(source: $source, data: ['name' => 'N']);

        $this->assertSame($expected, $html);
    }

    public function testMissingHtmlSnippetThrows(): void
    {
        $templateFile = $this->writeTemplate(source: "{tst:snippet name='missing.html'}");

        $this->expectEngineException(
            oldClass: Exception::class,
            oldMessage: 'Could not find template file: ' . AbstractTemplateIncludeTagsTestCase::fixtureDirectory() . 'snippets/missing.html',
            newMessage: 'Snippet not found: missing.html in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile);
    }

    public function testSnippetNameLeavingTheSnippetsDirectory(): void
    {
        // Differs: the old engine renders a snippet outside of the snippets directory, the new engine rejects it
        $templateFile = $this->writeTemplate(source: "{tst:snippet name='../sub.html'}");
        if ($this->isNewEngine()) {
            $this->expectException(TemplateException::class);
            $this->expectExceptionMessageIs(
                'The snippet name "../sub.html" leaves the snippets directory in ' . $templateFile . ' on line 1',
            );
        }

        $html = $this->renderFile(templateFile: $templateFile, data: ['name' => 'N']);

        $this->assertSame('sub:N', $html);
    }
}
