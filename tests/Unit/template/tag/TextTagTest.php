<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template\tag;

use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\html\HtmlText;
use actra\yuf\template\TemplateException;
use actra\yuf\tests\Double\template\NewEngineTestCase;
use actra\yuf\tests\Double\template\StringableValue;
use ArrayObject;

final class TextTagTest extends NewEngineTestCase
{
    public function testPlainStringIsEscaped(): void
    {
        $html = $this->render(source: "{tst:text value='x'}", data: ['x' => '<a href="/">T&C \'q\'</a>']);

        $this->assertSame('&lt;a href=&quot;/&quot;&gt;T&amp;C &#039;q&#039;&lt;/a&gt;', $html);
    }

    public function testElementForm(): void
    {
        $this->assertSame('[&lt;]', $this->render(source: '[<tst:text value="x"/>]', data: ['x' => '<']));
    }

    public function testElementFormWithBodyIgnoresTheBody(): void
    {
        $this->assertSame('v', $this->render(source: '<tst:text value="x">ignored</tst:text>', data: ['x' => 'v']));
    }

    public function testNumbersBooleansAndNull(): void
    {
        $source = "{tst:text value='i'}|{tst:text value='f'}|{tst:text value='t'}|{tst:text value='n'}|{tst:text value='z'}";

        $html = $this->render(source: $source, data: ['i' => 7, 'f' => 1.5, 't' => true, 'n' => null, 'z' => false]);

        $this->assertSame('7|1.5|1||', $html);
    }

    public function testStringableIsEscaped(): void
    {
        $html = $this->render(source: "{tst:text value='x'}", data: ['x' => new StringableValue(value: '<i>')]);

        $this->assertSame('&lt;i&gt;', $html);
    }

    public function testValueOfAnArrayObjectInTheData(): void
    {
        $html = $this->render(source: "{tst:text value='o.k'}", data: ['o' => new ArrayObject(array: ['k' => '&'])]);

        $this->assertSame('&amp;', $html);
    }



    public function testHtmlTextCreatedFromHtmlIsNotEscapedAgain(): void
    {
        $replacements = new HtmlReplacementCollection();
        $replacements->addHtmlText(identifier: 'x', htmlText: HtmlText::encoded(textContent: '<b>a</b> &amp; b'));

        $this->assertSame('<b>a</b> &amp; b', $this->render(source: "{tst:text value='x'}", data: $replacements));
    }

    public function testHtmlTextCreatedFromTextIsEscapedOnlyOnce(): void
    {
        $replacements = new HtmlReplacementCollection();
        $replacements->addHtmlText(identifier: 'x', htmlText: HtmlText::unencoded(textContent: '<b> & "q"'));

        $this->assertSame('&lt;b&gt; &amp; &quot;q&quot;', $this->render(source: "{tst:text value='x'}", data: $replacements));
    }

    public function testArrayCannotBeOutput(): void
    {
        $templateFile = $this->writeTemplate(source: "a\n{tst:text value='x'}");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'Cannot output a value of type array, only text, numbers, booleans and null in ' . $templateFile . ' on line 2',
        );

        $this->renderFile(templateFile: $templateFile, data: ['x' => [1]]);
    }

    public function testMissingValueAttributeThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '{tst:text}');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs('Missing attribute "value" in ' . $templateFile . ' on line 1');

        $this->renderFile(templateFile: $templateFile);
    }
}
