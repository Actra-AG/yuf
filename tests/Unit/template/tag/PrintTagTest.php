<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template\tag;

use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\template\StringableValue;
use actra\yuf\tests\Double\template\TemplateEngineTestCase;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

final class PrintTagTest extends TemplateEngineTestCase
{
    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function valueProvider(): iterable
    {
        yield 'string is escaped' => ['<b>a</b> & "q"', '&lt;b&gt;a&lt;/b&gt; &amp; &quot;q&quot;'];
        yield 'int' => [42, '42'];
        yield 'float' => [1.5, '1.5'];
        yield 'true' => [true, '1'];
        yield 'false' => [false, ''];
        yield 'null' => [null, ''];
        yield 'Stringable' => [new StringableValue(value: '<i>'), '&lt;i&gt;'];
        yield 'DateTime' => [new DateTime(datetime: '2026-01-02 03:04:05'), '2026-01-02 03:04:05'];
        yield 'DateTimeImmutable' => [new DateTimeImmutable(datetime: '2026-05-06 07:08:09'), '2026-05-06 07:08:09'];
        yield 'array is dumped and escaped' => [
            ['a' => '<1>'],
            "Array\n(\n    [a] =&gt; &lt;1&gt;\n)\n",
        ];
        yield 'object is dumped and escaped' => [
            (object) ['a' => '<1>'],
            "stdClass Object\n(\n    [a] =&gt; &lt;1&gt;\n)\n",
        ];
    }

    #[DataProvider('valueProvider')]
    public function testInlineForm(mixed $value, string $expected): void
    {
        $this->assertSame($expected, $this->render(source: "{tst:print var='x'}", data: ['x' => $value]));
    }

    #[DataProvider('valueProvider')]
    public function testElementForm(mixed $value, string $expected): void
    {
        $this->assertSame($expected, $this->render(source: '<tst:print var="x"/>', data: ['x' => $value]));
    }

    public function testPropertyOfAnObject(): void
    {
        $object = new stdClass();
        $object->a = '&';

        $this->assertSame('&amp;', $this->render(source: "{tst:print var='o.a'}", data: ['o' => $object]));
    }

    public function testHtmlOfTheHtmlClassesIsNotEscapedAgain(): void
    {
        $replacements = new HtmlReplacementCollection();
        $replacements->addHtmlText(identifier: 'x', htmlText: HtmlText::fromHtml(html: '<b>a</b>'));

        $this->assertSame('<b>a</b>', $this->render(source: "{tst:print var='x'}", data: $replacements));
    }
}
