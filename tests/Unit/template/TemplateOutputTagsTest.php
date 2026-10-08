<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template;

use actra\yuf\template\TemplateException;
use actra\yuf\tests\Double\template\TemplateEngineTestCase;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The `print`, `date` and `options` tags (docs/template-engine/design.md, section 3): their output is escaped.
 */
final class TemplateOutputTagsTest extends TemplateEngineTestCase
{
    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function printProvider(): iterable
    {
        yield 'string' => ['<b>a</b>', '&lt;b&gt;a&lt;/b&gt;'];
        yield 'int' => [42, '42'];
        yield 'float' => [1.5, '1.5'];
        yield 'true' => [true, '1'];
        yield 'false' => [false, ''];
        yield 'null' => [null, ''];
        yield 'DateTime is formatted' => [new DateTime(datetime: '2026-01-02 03:04:05'), '2026-01-02 03:04:05'];
        yield 'DateTimeImmutable' => [
            new DateTimeImmutable(datetime: '2026-01-02 03:04:05', timezone: new DateTimeZone(timezone: 'UTC')),
            '2026-01-02 03:04:05',
        ];
        yield 'array' => [
            ['a' => 1, 'b' => ['c' => '<2>']],
            "Array\n(\n    [a] =&gt; 1\n    [b] =&gt; Array\n        (\n            [c] =&gt; &lt;2&gt;\n        "
                . ")\n\n)\n",
        ];
    }

    #[DataProvider('printProvider')]
    public function testPrintInline(mixed $value, string $expected): void
    {
        $this->assertSame($expected, $this->render(source: "{tst:print var='x'}", data: ['x' => $value]));
    }

    #[DataProvider('printProvider')]
    public function testPrintElement(mixed $value, string $expected): void
    {
        $this->assertSame($expected, $this->render(source: '<tst:print var="x"/>', data: ['x' => $value]));
    }

    public function testPrintOfAnObjectProperty(): void
    {
        $html = $this->render(source: "{tst:print var='o.a'}", data: ['o' => (object) ['a' => 'v']]);

        $this->assertSame('v', $html);
    }

    public function testDateElementOutputsTheCurrentTime(): void
    {
        $html = $this->render(source: '[<tst:date format="Y-m-d H:i"/>]');

        $this->assertSame('[2026-01-02 03:04]', $html);
    }

    public function testDateFormatCharactersAreOutputLikeDate(): void
    {
        $html = $this->render(source: '<tst:date format="\Y\e\a\r: Y"/>');

        $this->assertSame('Year: 2026', $html);
    }

    public function testDateInline(): void
    {
        $templateFile = $this->writeTemplate(source: "{tst:date format='Y'}");

        $this->assertSame('2026', $this->renderFile(templateFile: $templateFile));
    }

    public function testOptionsWithSelectedValue(): void
    {
        $html = $this->render(
            source: '<tst:options options="o" selected="s"/>',
            data: ['o' => ['1' => 'One', '2' => 'Two'], 's' => '2'],
        );

        $this->assertSame("<option value=\"1\">One</option>\n<option value=\"2\" selected>Two</option>\n", $html);
    }

    public function testOptionsWithSeveralSelectedValues(): void
    {
        $html = $this->render(
            source: '<tst:options options="o" selected="s"/>',
            data: ['o' => [1 => 'One', 2 => 'Two', 3 => 'Three'], 's' => [1, '3']],
        );

        $this->assertSame(
            "<option value=\"1\" selected>One</option>\n<option value=\"2\">Two</option>\n<option value=\"3\" "
                . "selected>Three</option>\n",
            $html,
        );
    }

    public function testOptionsWithGroupAndNoSelection(): void
    {
        $html = $this->render(
            source: '<tst:options options="o" selected="s"/>',
            data: ['o' => ['a' => 'A', 'g' => ['b' => 'B']], 's' => null],
        );

        $this->assertSame(
            "<option value=\"a\">A</option>\n<optgroup label=\"g\">\n<option value=\"b\">B</option>\n</optgroup>\n",
            $html,
        );
    }

    public function testOptionsKeysAndLabels(): void
    {
        $html = $this->render(
            source: '<tst:options options="o" selected="s"/>',
            data: ['o' => ['a"b' => 'T<w>o & <i>x</i>'], 's' => ''],
        );

        $this->assertSame("<option value=\"a&quot;b\">T&lt;w&gt;o &amp; &lt;i&gt;x&lt;/i&gt;</option>\n", $html);
    }

    public function testOptionsWithEmptyListRenderNothing(): void
    {
        $html = $this->render(source: '[<tst:options options="o" selected="s"/>]', data: ['o' => [], 's' => '']);

        $this->assertSame('[]', $html);
    }

    public function testOptionsWithoutSelectedAttribute(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:options options="o"/>');

        $html = $this->renderFile(templateFile: $templateFile, data: ['o' => ['1' => 'One']]);

        $this->assertSame("<option value=\"1\">One</option>\n", $html);
    }

    public function testOptionsWithoutOptionsAttributeThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:options selected="s"/>');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs('Missing attribute "options" in ' . $templateFile . ' on line 1');

        $this->renderFile(templateFile: $templateFile, data: ['s' => '']);
    }
}
