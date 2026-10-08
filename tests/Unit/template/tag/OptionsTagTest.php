<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template\tag;

use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\html\HtmlText;
use actra\yuf\html\HtmlTextCollection;
use actra\yuf\template\TemplateException;
use actra\yuf\tests\Double\template\NewEngineTestCase;
use actra\yuf\tests\Double\template\StringableValue;

final class OptionsTagTest extends NewEngineTestCase
{
    private const string SOURCE = '<tst:options options="o" selected="s"/>';

    public function testOptionsWithOneSelectedValue(): void
    {
        $html = $this->render(source: OptionsTagTest::SOURCE, data: ['o' => ['1' => 'One', '2' => 'Two'], 's' => '2']);

        $this->assertSame("<option value=\"1\">One</option>\n<option value=\"2\" selected>Two</option>\n", $html);
    }

    public function testSelectedIsOptional(): void
    {
        $html = $this->render(source: '<tst:options options="o"/>', data: ['o' => ['a' => 'A']]);

        $this->assertSame("<option value=\"a\">A</option>\n", $html);
    }

    public function testSeveralSelectedValues(): void
    {
        $html = $this->render(
            source: OptionsTagTest::SOURCE,
            data: ['o' => [1 => 'One', 2 => 'Two', 3 => 'Three'], 's' => [1, '3']],
        );

        $this->assertSame(
            "<option value=\"1\" selected>One</option>\n<option value=\"2\">Two</option>\n<option value=\"3\" selected>Three</option>\n",
            $html,
        );
    }

    public function testSelectionComparesTheText(): void
    {
        $data = ['o' => [1 => 'One', 'a' => 'A', '' => 'Empty'], 's' => '1'];

        $this->assertStringContainsString('<option value="1" selected>', $this->render(source: OptionsTagTest::SOURCE, data: $data));
        $this->assertStringContainsString('<option value="1" selected>', $this->render(source: OptionsTagTest::SOURCE, data: [...$data, 's' => 1]));
        $this->assertStringContainsString('<option value="1" selected>', $this->render(source: OptionsTagTest::SOURCE, data: [...$data, 's' => new StringableValue(value: '1')]));
        $this->assertStringNotContainsString('selected', $this->render(source: OptionsTagTest::SOURCE, data: [...$data, 's' => '01']));
        // A boolean has the text '1' (as in the output of a text tag)
        $this->assertStringContainsString('<option value="1" selected>', $this->render(source: OptionsTagTest::SOURCE, data: [...$data, 's' => true]));
    }

    public function testNullAndEmptySelectionSelectNothingExceptTheEmptyKey(): void
    {
        $data = ['o' => ['a' => 'A', '' => 'Empty']];

        $this->assertStringNotContainsString('selected', $this->render(source: OptionsTagTest::SOURCE, data: [...$data, 's' => null]));
        $this->assertStringNotContainsString('selected', $this->render(source: OptionsTagTest::SOURCE, data: [...$data, 's' => []]));
        $this->assertStringContainsString('<option value="" selected>', $this->render(source: OptionsTagTest::SOURCE, data: [...$data, 's' => '']));
    }

    public function testNestedArrayBecomesAnOptgroup(): void
    {
        $html = $this->render(
            source: OptionsTagTest::SOURCE,
            data: ['o' => ['a' => 'A', 'g' => ['b' => 'B', 'c' => 'C']], 's' => 'c'],
        );

        $this->assertSame(
            "<option value=\"a\">A</option>\n<optgroup label=\"g\">\n<option value=\"b\">B</option>\n<option value=\"c\" selected>C</option>\n</optgroup>\n",
            $html,
        );
    }

    public function testKeysAndLabelsAreEscaped(): void
    {
        $html = $this->render(
            source: OptionsTagTest::SOURCE,
            data: ['o' => ['a"b' => 'T<w>o & <i>x</i>', 'g<' => ['x' => '"q"']], 's' => 'a"b'],
        );

        $this->assertSame(
            "<option value=\"a&quot;b\" selected>T&lt;w&gt;o &amp; &lt;i&gt;x&lt;/i&gt;</option>\n<optgroup label=\"g&lt;\">\n<option value=\"x\">&quot;q&quot;</option>\n</optgroup>\n",
            $html,
        );
    }

    public function testLabelsOfTheHtmlClassesAreNotEscapedAgain(): void
    {
        $replacements = new HtmlReplacementCollection();
        $replacements->addHtmlTextCollection(
            identifier: 'o',
            htmlTextCollection: new HtmlTextCollection(
                items: [HtmlText::fromHtml(html: '<b>One</b>'), HtmlText::fromText(text: '<Two>')],
            ),
        );
        $replacements->addHtml(identifier: 's', html: '1');

        $html = $this->render(source: OptionsTagTest::SOURCE, data: $replacements);

        $this->assertSame(
            "<option value=\"0\"><b>One</b></option>\n<option value=\"1\" selected>&lt;Two&gt;</option>\n",
            $html,
        );
    }

    public function testEmptyListRendersNothing(): void
    {
        $this->assertSame('[]', $this->render(source: '[' . OptionsTagTest::SOURCE . ']', data: ['o' => [], 's' => '']));
    }

    public function testOptionsThatAreNotAnArrayThrow(): void
    {
        $templateFile = $this->writeTemplate(source: OptionsTagTest::SOURCE);

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs('The options "o" must be an array, got string in ' . $templateFile . ' on line 1');

        $this->renderFile(templateFile: $templateFile, data: ['o' => 'text', 's' => '']);
    }

    public function testLabelThatIsAnObjectThrows(): void
    {
        $templateFile = $this->writeTemplate(source: OptionsTagTest::SOURCE);

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'Cannot output a value of type stdClass, only text, numbers, booleans and null in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile, data: ['o' => ['a' => (object) []], 's' => '']);
    }

    public function testMissingOptionsAttributeThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:options selected="s"/>');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs('Missing attribute "options" in ' . $templateFile . ' on line 1');

        $this->renderFile(templateFile: $templateFile, data: ['s' => '']);
    }

    public function testMissingSelectedValueThrows(): void
    {
        $templateFile = $this->writeTemplate(source: OptionsTagTest::SOURCE);

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'The template data "s" does not exist. Check that the view provides a replacement with this identifier in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile, data: ['o' => ['a' => 'A']]);
    }
}
