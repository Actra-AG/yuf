<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template;

use actra\yuf\template\TemplateException;
use actra\yuf\tests\Double\template\TemplateEngineTestCase;

/**
 * Text around the tags, whitespace, and what the parser accepts and rejects (design sections 1 and 2).
 */
final class TemplateSyntaxTest extends TemplateEngineTestCase
{
    public function testTemplateWithoutTagsIsCopiedUnchanged(): void
    {
        $this->assertSame("hello <b>&amp;</b>\n", $this->render(source: "hello <b>&amp;</b>\n"));
    }

    public function testLineBreakRightAfterATagIsSwallowed(): void
    {
        // The compiled code ends with a PHP closing tag, and PHP swallows a single line break directly after it
        $this->assertSame('v', $this->render(source: "{tst:text value='x'}\n", data: ['x' => 'v']));
        $this->assertSame("v\n", $this->render(source: "{tst:text value='x'}\n\n", data: ['x' => 'v']));
        $this->assertSame("v \n", $this->render(source: "{tst:text value='x'} \n", data: ['x' => 'v']));
    }

    public function testEmptyTemplate(): void
    {
        $templateFile = $this->writeTemplate(source: '');

        $this->assertSame('', $this->renderFile(templateFile: $templateFile));
    }

    public function testMissingTemplateFileThrows(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs('Template file not found: /nonexistent/template.html');

        $this->renderFile(templateFile: '/nonexistent/template.html');
    }

    public function testUnknownElementTagThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:foo/>');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs('Unknown template tag "foo" in ' . $templateFile . ' on line 1');

        $this->renderFile(templateFile: $templateFile);
    }

    public function testUnknownInlineTagThrows(): void
    {
        $templateFile = $this->writeTemplate(source: "{tst:foo value='x'}");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs('Unknown template tag "foo" in ' . $templateFile . ' on line 1');

        $this->renderFile(templateFile: $templateFile);
    }

    public function testElementTagWithSingleQuotedAttributesIsNotRecognized(): void
    {
        $this->assertSame("<tst:text value='x'/>", $this->render(source: "<tst:text value='x'/>", data: ['x' => 'v']));
    }

    public function testTagsOfOtherNamespacesAreNotTouched(): void
    {
        $this->assertSame(
            '<other:text value="x"/>{other:text value=\'x\'}',
            $this->render(source: '<other:text value="x"/>{other:text value=\'x\'}'),
        );
    }

    public function testMismatchedClosingTag(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:if compare="v" operator="eq" against="a">Y</tst:for>');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'The closing tag </tst:for> does not match the opening tag <tst:if> of line 1 in ' . $templateFile
                . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile, data: ['v' => 'a']);
    }

    public function testPhpCodeInTheTemplate(): void
    {
        $templateFile = $this->writeTemplate(source: "a\n<?php echo 'x'; ?>b");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'PHP code is not allowed in a template, prepare the values in the view instead in ' . $templateFile
                . ' on line 2',
        );

        $this->renderFile(templateFile: $templateFile);
    }

    public function testHtmlCommentsAndDoctypeAreKept(): void
    {
        $source = "<!DOCTYPE html>\n<!-- comment -->\n<p>x</p>";

        $this->assertSame($source, $this->render(source: $source));
    }

    public function testCompiledTemplateIsCachedAndRenderedAgain(): void
    {
        $templateFile = $this->writeTemplate(source: "[{tst:text value='x'}]");

        $first = $this->renderFile(templateFile: $templateFile, data: ['x' => 1]);
        $second = $this->renderFile(templateFile: $templateFile, data: ['x' => 2]);

        $this->assertSame('[1]', $first);
        $this->assertSame('[2]', $second);
    }
}
