<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template;

use actra\yuf\tests\Double\template\TemplateCharacterizationTestCase;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Characterization of the template engine before the rewrite (docs/template-engine/plan.md, step 1): print, date and
 * options. None of them escapes its output today.
 */
final class TemplateOutputTagsTest extends TemplateCharacterizationTestCase
{
    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function printProvider(): iterable
    {
        yield 'string is not escaped' => ['<b>a</b>', '<b>a</b>'];
        yield 'int' => [42, '42'];
        yield 'float' => [1.5, '1.5'];
        yield 'true' => [true, '1'];
        yield 'false' => [false, ''];
        yield 'null' => [null, ''];
        yield 'DateTime is formatted' => [new DateTime(datetime: '2026-01-02 03:04:05'), '2026-01-02 03:04:05'];
        // Differs from docs/template-engine/design.md: only DateTime is formatted, not DateTimeInterface
        yield 'DateTimeImmutable is dumped' => [
            new DateTimeImmutable(datetime: '2026-01-02 03:04:05', timezone: new DateTimeZone(timezone: 'UTC')),
            "DateTimeImmutable Object\n(\n    [date] => 2026-01-02 03:04:05.000000\n    [timezone_type] => 3\n    [timezone] => UTC\n)\n",
        ];
        yield 'array' => [['a' => 1, 'b' => ['c' => '<2>']], "Array\n(\n    [a] => 1\n    [b] => Array\n        (\n            [c] => <2>\n        )\n\n)\n"];
    }

    #[DataProvider('printProvider')]
    public function testPrintInline(mixed $value, string $expected): void
    {
        $this->assertSame($expected, $this->renderSource(source: "{tst:print var='x'}", data: ['x' => $value]));
    }

    #[DataProvider('printProvider')]
    public function testPrintElement(mixed $value, string $expected): void
    {
        $this->assertSame($expected, $this->renderSource(source: '<tst:print var="x"/>', data: ['x' => $value]));
    }

    public function testPrintOfAnObjectProperty(): void
    {
        $html = $this->renderSource(source: "{tst:print var='o.a'}", data: ['o' => (object) ['a' => 'v']]);

        $this->assertSame('v', $html);
    }

    public function testDateElementUsesTheRealClock(): void
    {
        $html = $this->renderSource(source: '[<tst:date format="Y-m-d H:i"/>]');

        $this->assertMatchesRegularExpression('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}\]$/', $html);
    }

    public function testDateFormatCharactersAreOutputLikeDate(): void
    {
        $html = $this->renderSource(source: '<tst:date format="\Y\e\a\r: Y"/>');

        $this->assertMatchesRegularExpression('/^Year: \d{4}$/', $html);
    }

    public function testDateCannotBeUsedInline(): void
    {
        $templateFile = $this->writeTemplate(source: "{tst:date format='Y'}");

        $this->expectException(Exception::class);
        $this->expectExceptionMessageIs(
            'Error while processing the template file ' . $templateFile . ': CustomTag "actra\yuf\template\customtags\DateTag" is not allowed to use inline.',
        );

        $this->renderFile(templateFile: $templateFile);
    }

    public function testOptionsWithSelectedValue(): void
    {
        $html = $this->renderSource(
            source: '<tst:options options="o" selected="s"/>',
            data: ['o' => ['1' => 'One', '2' => 'Two'], 's' => '2'],
        );

        $this->assertSame("<option value=\"1\">One</option>\n<option value=\"2\" selected>Two</option>\n", $html);
    }

    public function testOptionsWithSeveralSelectedValues(): void
    {
        $html = $this->renderSource(
            source: '<tst:options options="o" selected="s"/>',
            data: ['o' => [1 => 'One', 2 => 'Two', 3 => 'Three'], 's' => [1, '3']],
        );

        $this->assertSame(
            "<option value=\"1\" selected>One</option>\n<option value=\"2\">Two</option>\n<option value=\"3\" selected>Three</option>\n",
            $html,
        );
    }

    public function testOptionsWithGroupAndNoSelection(): void
    {
        $html = $this->renderSource(
            source: '<tst:options options="o" selected="s"/>',
            data: ['o' => ['a' => 'A', 'g' => ['b' => 'B']], 's' => null],
        );

        $this->assertSame(
            "<option value=\"a\">A</option>\n<optgroup label=\"g\">\n<option value=\"b\">B</option>\n</optgroup>\n",
            $html,
        );
    }

    public function testOptionsAreNotEscaped(): void
    {
        $html = $this->renderSource(
            source: '<tst:options options="o" selected="s"/>',
            data: ['o' => ['a"b' => 'T<w>o & <i>x</i>'], 's' => ''],
        );

        $this->assertSame("<option value=\"a\"b\">T<w>o & <i>x</i></option>\n", $html);
    }

    public function testOptionsWithEmptyListRenderNothing(): void
    {
        $html = $this->renderSource(source: '[<tst:options options="o" selected="s"/>]', data: ['o' => [], 's' => '']);

        $this->assertSame('[]', $html);
    }

    /**
     * Differs from docs/template-engine/design.md: selected is optional there.
     */
    public function testOptionsWithoutSelectedAttributeThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:options options="o"/>');

        $this->expectException(Exception::class);
        $this->expectExceptionCode(1);
        $this->expectExceptionMessageIs(
            'The data with offset "" does not exist for template file ' . $templateFile . '. Check, if the correct BaseView class has been found/executed and set the correct replacements.',
        );

        $this->renderFile(templateFile: $templateFile, data: ['o' => ['1' => 'One']]);
    }

    public function testOptionsWithoutOptionsAttributeThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:options selected="s"/>');

        $this->expectException(Exception::class);
        $this->expectExceptionMessageIs(
            'Error while processing the template file ' . $templateFile . ': Could not parse the template: Missing attribute \'options\' for custom tag \'options\' in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile, data: ['s' => '']);
    }
}
