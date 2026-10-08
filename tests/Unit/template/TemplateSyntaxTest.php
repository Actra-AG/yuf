<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template;

use actra\yuf\tests\Double\template\TemplateCharacterizationTestCase;
use Exception;

/**
 * Characterization of the template engine before the rewrite (docs/template-engine/plan.md, step 1): text around the
 * tags, whitespace, and what the parser accepts today. Every test marked "Differs" pins behaviour that the rewrite
 * changes on purpose (design sections 1 and 2).
 */
final class TemplateSyntaxTest extends TemplateCharacterizationTestCase
{
    public function testTemplateWithoutTagsIsCopiedUnchanged(): void
    {
        $this->assertSame("hello <b>&amp;</b>\n", $this->renderSource(source: "hello <b>&amp;</b>\n"));
    }

    public function testLineBreakRightAfterATagIsSwallowed(): void
    {
        // The compiled code ends with a PHP closing tag, and PHP swallows a single line break directly after it
        $this->assertSame('v', $this->renderSource(source: "{tst:text value='x'}\n", data: ['x' => 'v']));
        $this->assertSame("v\n", $this->renderSource(source: "{tst:text value='x'}\n\n", data: ['x' => 'v']));
        $this->assertSame("v \n", $this->renderSource(source: "{tst:text value='x'} \n", data: ['x' => 'v']));
    }

    public function testEmptyTemplateThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '');

        $this->expectException(Exception::class);
        $this->expectExceptionMessageIs('Invalid template-file: ' . $templateFile);

        $this->renderFile(templateFile: $templateFile);
    }

    public function testMissingTemplateFileThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageIs('Could not find template file: /nonexistent/template.html');

        $this->renderFile(templateFile: '/nonexistent/template.html');
    }

    public function testUnknownElementTagThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:foo/>');

        $this->expectException(Exception::class);
        $this->expectExceptionMessageIs(
            'Error while processing the template file ' . $templateFile . ': The custom tag "foo" is not registered in this template engine instance',
        );

        $this->renderFile(templateFile: $templateFile);
    }

    public function testUnknownInlineTagThrows(): void
    {
        $templateFile = $this->writeTemplate(source: "{tst:foo value='x'}");

        $this->expectException(Exception::class);
        $this->expectExceptionMessageIs(
            'Error while processing the template file ' . $templateFile . ': The custom tag "foo" is not registered in this template engine instance',
        );

        $this->renderFile(templateFile: $templateFile);
    }

    public function testElementTagWithSingleQuotedAttributesIsNotRecognized(): void
    {
        $this->assertSame("<tst:text value='x'/>", $this->renderSource(source: "<tst:text value='x'/>", data: ['x' => 'v']));
    }

    public function testTagsOfOtherNamespacesAreNotTouched(): void
    {
        $this->assertSame('<other:text value="x"/>{other:text value=\'x\'}', $this->renderSource(source: '<other:text value="x"/>{other:text value=\'x\'}'));
    }

    public function testMismatchedClosingTagIsAccepted(): void
    {
        // Differs: a close tag that does not match the open tag is a syntax error
        $html = $this->renderSource(
            source: '<tst:if compare="v" operator="eq" against="a">Y</tst:for>',
            data: ['v' => 'a'],
        );

        $this->assertSame('Y', $html);
    }

    public function testPhpCodeInTheTemplateIsExecuted(): void
    {
        // Differs: <?php in a template is a syntax error
        $this->assertSame('axb', $this->renderSource(source: 'a<?php echo \'x\'; ?>b'));
    }

    public function testHtmlCommentsAndDoctypeAreKept(): void
    {
        $source = "<!DOCTYPE html>\n<!-- comment -->\n<p>x</p>";

        $this->assertSame($source, $this->renderSource(source: $source));
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
