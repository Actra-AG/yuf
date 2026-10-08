<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template\tag;

use actra\yuf\template\TemplateException;
use actra\yuf\tests\Double\template\TemplateEngineTestCase;

/**
 * The renderer of TemplateEngineTestCase has a fixed clock at 2026-01-02 03:04:05.
 */
final class DateTagTest extends TemplateEngineTestCase
{
    public function testElementForm(): void
    {
        $this->assertSame('[2026-01-02 03:04]', $this->render(source: '[<tst:date format="Y-m-d H:i"/>]'));
    }

    public function testInlineForm(): void
    {
        $this->assertSame('02.01.2026', $this->render(source: "{tst:date format='d.m.Y'}"));
    }

    public function testEscapedFormatCharactersAreOutputLikeDateTimeFormat(): void
    {
        $this->assertSame('Year: 2026', $this->render(source: '<tst:date format="\Y\e\a\r: Y"/>'));
    }

    public function testOutputIsEscaped(): void
    {
        $this->assertSame('&lt;b&gt;2026&lt;/b&gt;', $this->render(source: '<tst:date format="\<\b\>Y\<\/\b\>"/>'));
    }

    public function testMissingFormatThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:date/>');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs('Missing attribute "format" in ' . $templateFile . ' on line 1');

        $this->renderFile(templateFile: $templateFile);
    }
}
