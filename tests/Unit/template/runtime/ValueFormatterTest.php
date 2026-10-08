<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template\runtime;

use actra\yuf\template\runtime\TrustedHtml;
use actra\yuf\template\runtime\ValueFormatter;
use actra\yuf\template\TemplateException;
use actra\yuf\tests\Double\template\StringableValue;
use ArrayObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ValueFormatterTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function escapeProvider(): iterable
    {
        yield 'text with markup' => ['<b>a</b>', '&lt;b&gt;a&lt;/b&gt;'];
        yield 'ampersand' => ['a & b', 'a &amp; b'];
        yield 'double quote' => ['say "x"', 'say &quot;x&quot;'];
        yield 'single quote' => ["it's", 'it&#039;s'];
        yield 'already escaped text is escaped again' => ['&lt;', '&amp;lt;'];
        yield 'invalid UTF-8 is substituted' => ["a\xB1b", "a\u{FFFD}b"];
        yield 'umlauts stay' => ['Grüezi', 'Grüezi'];
        yield 'empty string' => ['', ''];
        yield 'int' => [42, '42'];
        yield 'zero' => [0, '0'];
        yield 'float' => [1.5, '1.5'];
        yield 'true' => [true, '1'];
        yield 'false' => [false, ''];
        yield 'null' => [null, ''];
        yield 'Stringable' => [new StringableValue(value: '<i>'), '&lt;i&gt;'];
        yield 'trusted HTML is not escaped again' => [
            new TrustedHtml(html: '<b>a</b> &amp; "q"'),
            '<b>a</b> &amp; "q"',
        ];
    }

    #[DataProvider('escapeProvider')]
    public function testEscape(mixed $value, string $expected): void
    {
        $this->assertSame($expected, new ValueFormatter()->escape(value: $value));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function textProvider(): iterable
    {
        yield 'string is not escaped' => ['<b>', '<b>'];
        yield 'int' => [7, '7'];
        yield 'true' => [true, '1'];
        yield 'false' => [false, ''];
        yield 'null' => [null, ''];
        yield 'Stringable' => [new StringableValue(value: 'x&y'), 'x&y'];
        yield 'trusted HTML' => [new TrustedHtml(html: '<b>'), '<b>'];
    }

    #[DataProvider('textProvider')]
    public function testText(mixed $value, string $expected): void
    {
        $this->assertSame($expected, new ValueFormatter()->text(value: $value));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidValueProvider(): iterable
    {
        yield 'array' => [[1], 'array'];
        yield 'stdClass' => [new stdClass(), 'stdClass'];
        yield 'ArrayObject' => [new ArrayObject(), 'ArrayObject'];
    }

    #[DataProvider('invalidValueProvider')]
    public function testEscapeRejectsArraysAndObjects(mixed $value, string $type): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'Cannot output a value of type ' . $type . ', only text, numbers, booleans and null',
        );

        new ValueFormatter()->escape(value: $value);
    }

    #[DataProvider('invalidValueProvider')]
    public function testTextRejectsArraysAndObjects(mixed $value, string $type): void
    {
        $this->expectException(TemplateException::class);

        new ValueFormatter()->text(value: $value);
    }
}
