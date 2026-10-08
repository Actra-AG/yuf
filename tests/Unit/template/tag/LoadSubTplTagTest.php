<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template\tag;

use actra\yuf\template\TemplateException;
use actra\yuf\tests\Double\template\TemplateEngineTestCase;

final class LoadSubTplTagTest extends TemplateEngineTestCase
{
    public function testLiteralPath(): void
    {
        $html = $this->render(
            source: 'x<tst:loadSubTpl tplfile="' . TemplateEngineTestCase::fixtureDirectory() . 'sub.html"/>y',
            data: ['name' => 'N'],
        );

        $this->assertSame('xsub:Ny', $html);
    }

    public function testInlineForm(): void
    {
        $html = $this->render(
            source: "{tst:loadSubTpl tplfile='" . TemplateEngineTestCase::fixtureDirectory() . "sub.html'}",
            data: ['name' => 'N'],
        );

        $this->assertSame('sub:N', $html);
    }

    public function testPathFromTheTemplateData(): void
    {
        $html = $this->render(
            source: '[<tst:loadSubTpl tplfile="{file}"/>]',
            data: ['file' => TemplateEngineTestCase::fixtureDirectory() . 'sub.html', 'name' => '<N>'],
        );

        $this->assertSame('[sub:&lt;N&gt;]', $html);
    }

    public function testPathFromASelector(): void
    {
        $html = $this->render(
            source: '<tst:loadSubTpl tplfile="{page.file}"/>',
            data: ['page' => ['file' => TemplateEngineTestCase::fixtureDirectory() . 'sub.html'], 'name' => 'N'],
        );

        $this->assertSame('sub:N', $html);
    }

    public function testEmptyPathRendersNothing(): void
    {
        $this->assertSame('ab', $this->render(source: 'a<tst:loadSubTpl tplfile=""/>b'));
        $this->assertSame('ab', $this->render(source: 'a<tst:loadSubTpl tplfile="{file}"/>b', data: ['file' => '']));
        $this->assertSame('ab', $this->render(source: 'a<tst:loadSubTpl tplfile="{file}"/>b', data: ['file' => null]));
    }

    public function testSubTemplateSeesTheLoopVariable(): void
    {
        $html = $this->render(
            source: '<tst:for value="l" var="i"><tst:loadSubTpl tplfile="' . TemplateEngineTestCase::fixtureDirectory()
                . 'subLoop.html"/>;</tst:for>',
            data: ['l' => [1, 2]],
        );

        $this->assertSame('item:1;item:2;', $html);
    }

    public function testMissingTemplateFileThrowsWithTheLocationOfTheTag(): void
    {
        $templateFile = $this->writeTemplate(source: "a\n<tst:loadSubTpl tplfile=\"/nonexistent/sub.html\"/>");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'Template file not found: /nonexistent/sub.html in ' . $templateFile . ' on line 2',
        );

        $this->renderFile(templateFile: $templateFile);
    }

    public function testMissingDataKeyThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:loadSubTpl tplfile="{file}"/>');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'The template data "file" does not exist. Check that the view provides a replacement with this '
                . 'identifier in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile);
    }

    public function testErrorInTheSubTemplateNamesTheSubTemplate(): void
    {
        $subTemplate = $this->writeTemplate(source: "line 1\n{tst:text value='missing'}");
        $templateFile = $this->writeTemplate(source: '<tst:loadSubTpl tplfile="' . $subTemplate . '"/>');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'The template data "missing" does not exist. Check that the view provides a replacement with this '
                . 'identifier in ' . $subTemplate . ' on line 2',
        );

        $this->renderFile(templateFile: $templateFile);
    }

    public function testSyntaxErrorInTheSubTemplateNamesTheSubTemplate(): void
    {
        $subTemplate = $this->writeTemplate(source: "line 1\n</tst:if>");
        $templateFile = $this->writeTemplate(source: '<tst:loadSubTpl tplfile="' . $subTemplate . '"/>');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'Unexpected closing tag </tst:if> without an opening tag in ' . $subTemplate . ' on line 2',
        );

        $this->renderFile(templateFile: $templateFile);
    }

    public function testMissingAttributeThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:loadSubTpl/>');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs('Missing attribute "tplfile" in ' . $templateFile . ' on line 1');

        $this->renderFile(templateFile: $templateFile);
    }
}
