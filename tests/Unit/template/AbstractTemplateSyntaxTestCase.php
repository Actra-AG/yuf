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

/**
 * Characterization of the template engine before the rewrite (docs/template-engine/plan.md, step 1): text around the
 * tags, whitespace, and what the parser accepts today. Every test marked "Differs" pins behaviour that the rewrite
 * changes on purpose (design sections 1 and 2).
 */
abstract class AbstractTemplateSyntaxTestCase extends TemplateCharacterizationTestCase
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

    public function testEmptyTemplate(): void
    {
        // Differs: the old engine rejects an empty template, the new engine renders an empty string
        $templateFile = $this->writeTemplate(source: '');
        if (!$this->isNewEngine()) {
            $this->expectException(Exception::class);
            $this->expectExceptionMessageIs('Invalid template-file: ' . $templateFile);
        }

        $this->assertSame('', $this->renderFile(templateFile: $templateFile));
    }

    public function testMissingTemplateFileThrows(): void
    {
        $this->expectEngineException(
            oldClass: Exception::class,
            oldMessage: 'Could not find template file: /nonexistent/template.html',
            newMessage: 'Template file not found: /nonexistent/template.html',
        );

        $this->renderFile(templateFile: '/nonexistent/template.html');
    }

    public function testUnknownElementTagThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:foo/>');

        $this->expectEngineException(
            oldClass: Exception::class,
            oldMessage: 'Error while processing the template file ' . $templateFile . ': The custom tag "foo" is not registered in this template engine instance',
            newMessage: 'Unknown template tag "foo" in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile);
    }

    public function testUnknownInlineTagThrows(): void
    {
        $templateFile = $this->writeTemplate(source: "{tst:foo value='x'}");

        $this->expectEngineException(
            oldClass: Exception::class,
            oldMessage: 'Error while processing the template file ' . $templateFile . ': The custom tag "foo" is not registered in this template engine instance',
            newMessage: 'Unknown template tag "foo" in ' . $templateFile . ' on line 1',
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

    public function testMismatchedClosingTag(): void
    {
        // Differs: the old engine accepts a close tag that does not match the open tag, the new engine rejects it
        $templateFile = $this->writeTemplate(source: '<tst:if compare="v" operator="eq" against="a">Y</tst:for>');
        if ($this->isNewEngine()) {
            $this->expectException(TemplateException::class);
            $this->expectExceptionMessageIs(
                'The closing tag </tst:for> does not match the opening tag <tst:if> of line 1 in ' . $templateFile . ' on line 1',
            );
        }

        $this->assertSame('Y', $this->renderFile(templateFile: $templateFile, data: ['v' => 'a']));
    }

    public function testPhpCodeInTheTemplate(): void
    {
        // Differs: the old engine executes <?php in a template, the new engine rejects it
        $templateFile = $this->writeTemplate(source: "a\n<?php echo 'x'; ?>b");
        if ($this->isNewEngine()) {
            $this->expectException(TemplateException::class);
            $this->expectExceptionMessageIs(
                'PHP code is not allowed in a template, prepare the values in the view instead in ' . $templateFile . ' on line 2',
            );
        }

        $this->assertSame("a\nxb", $this->renderFile(templateFile: $templateFile));
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
