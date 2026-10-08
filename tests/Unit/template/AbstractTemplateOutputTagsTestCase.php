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
abstract class AbstractTemplateOutputTagsTestCase extends TemplateCharacterizationTestCase
{
    /**
     * The third value is the result of the new engine where it differs: it escapes the output, formats every
     * DateTimeInterface and escapes the print_r() dump.
     *
     * @return iterable<string, array{mixed, string}|array{mixed, string, string}>
     */
    public static function printProvider(): iterable
    {
        yield 'string' => ['<b>a</b>', '<b>a</b>', '&lt;b&gt;a&lt;/b&gt;'];
        yield 'int' => [42, '42'];
        yield 'float' => [1.5, '1.5'];
        yield 'true' => [true, '1'];
        yield 'false' => [false, ''];
        yield 'null' => [null, ''];
        yield 'DateTime is formatted' => [new DateTime(datetime: '2026-01-02 03:04:05'), '2026-01-02 03:04:05'];
        yield 'DateTimeImmutable' => [
            new DateTimeImmutable(datetime: '2026-01-02 03:04:05', timezone: new DateTimeZone(timezone: 'UTC')),
            "DateTimeImmutable Object\n(\n    [date] => 2026-01-02 03:04:05.000000\n    [timezone_type] => 3\n    [timezone] => UTC\n)\n",
            '2026-01-02 03:04:05',
        ];
        yield 'array' => [
            ['a' => 1, 'b' => ['c' => '<2>']],
            "Array\n(\n    [a] => 1\n    [b] => Array\n        (\n            [c] => <2>\n        )\n\n)\n",
            "Array\n(\n    [a] =&gt; 1\n    [b] =&gt; Array\n        (\n            [c] =&gt; &lt;2&gt;\n        )\n\n)\n",
        ];
    }

    #[DataProvider('printProvider')]
    public function testPrintInline(mixed $value, string $expected, ?string $expectedByNewEngine = null): void
    {
        $expected = $this->isNewEngine() && $expectedByNewEngine !== null ? $expectedByNewEngine : $expected;

        $this->assertSame($expected, $this->renderSource(source: "{tst:print var='x'}", data: ['x' => $value]));
    }

    #[DataProvider('printProvider')]
    public function testPrintElement(mixed $value, string $expected, ?string $expectedByNewEngine = null): void
    {
        $expected = $this->isNewEngine() && $expectedByNewEngine !== null ? $expectedByNewEngine : $expected;

        $this->assertSame($expected, $this->renderSource(source: '<tst:print var="x"/>', data: ['x' => $value]));
    }

    public function testPrintOfAnObjectProperty(): void
    {
        $html = $this->renderSource(source: "{tst:print var='o.a'}", data: ['o' => (object) ['a' => 'v']]);

        $this->assertSame('v', $html);
    }

    public function testDateElementOutputsTheCurrentTime(): void
    {
        $html = $this->renderSource(source: '[<tst:date format="Y-m-d H:i"/>]');

        // The old engine uses the real clock, the new engine the fixed clock of the renderer
        $this->assertMatchesRegularExpression('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}\]$/', $html);
    }

    public function testDateFormatCharactersAreOutputLikeDate(): void
    {
        $html = $this->renderSource(source: '<tst:date format="\Y\e\a\r: Y"/>');

        $this->assertMatchesRegularExpression('/^Year: \d{4}$/', $html);
    }

    public function testDateInline(): void
    {
        // Differs: the old engine does not allow date as an inline tag, the new engine does
        $templateFile = $this->writeTemplate(source: "{tst:date format='Y'}");
        if (!$this->isNewEngine()) {
            $this->expectException(Exception::class);
            $this->expectExceptionMessageIs(
                'Error while processing the template file ' . $templateFile . ': CustomTag "actra\yuf\template\customtags\DateTag" is not allowed to use inline.',
            );
        }

        $this->assertMatchesRegularExpression('/^\d{4}$/', $this->renderFile(templateFile: $templateFile));
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

    public function testOptionsKeysAndLabels(): void
    {
        // Differs: the old engine does not escape keys and labels, the new engine does
        $html = $this->renderSource(
            source: '<tst:options options="o" selected="s"/>',
            data: ['o' => ['a"b' => 'T<w>o & <i>x</i>'], 's' => ''],
        );

        $this->assertSame(
            $this->forEngine(
                old: "<option value=\"a\"b\">T<w>o & <i>x</i></option>\n",
                new: "<option value=\"a&quot;b\">T&lt;w&gt;o &amp; &lt;i&gt;x&lt;/i&gt;</option>\n",
            ),
            $html,
        );
    }

    public function testOptionsWithEmptyListRenderNothing(): void
    {
        $html = $this->renderSource(source: '[<tst:options options="o" selected="s"/>]', data: ['o' => [], 's' => '']);

        $this->assertSame('[]', $html);
    }

    public function testOptionsWithoutSelectedAttribute(): void
    {
        // Differs: the old engine requires selected, in the new engine it is optional
        $templateFile = $this->writeTemplate(source: '<tst:options options="o"/>');
        if (!$this->isNewEngine()) {
            $this->expectException(Exception::class);
            $this->expectExceptionCode(1);
            $this->expectExceptionMessageIs(
                'The data with offset "" does not exist for template file ' . $templateFile . '. Check, if the correct BaseView class has been found/executed and set the correct replacements.',
            );
        }

        $html = $this->renderFile(templateFile: $templateFile, data: ['o' => ['1' => 'One']]);

        $this->assertSame("<option value=\"1\">One</option>\n", $html);
    }

    public function testOptionsWithoutOptionsAttributeThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:options selected="s"/>');

        $this->expectEngineException(
            oldClass: Exception::class,
            oldMessage: 'Error while processing the template file ' . $templateFile . ': Could not parse the template: Missing attribute \'options\' for custom tag \'options\' in ' . $templateFile . ' on line 1',
            newMessage: 'Missing attribute "options" in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile, data: ['s' => '']);
    }
}
