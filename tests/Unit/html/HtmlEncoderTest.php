<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\html;

use actra\yuf\html\HtmlEncoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HtmlEncoderTest extends TestCase
{
    /**
     * @return iterable<string, array{string|float|int|bool|null, string}>
     */
    public static function encodeProvider(): iterable
    {
        yield 'null' => [null, ''];
        yield 'empty string' => ['', ''];
        yield 'plain text' => ['Hello World', 'Hello World'];
        yield 'angle brackets' => ['<b>x</b>', '&lt;b&gt;x&lt;/b&gt;'];
        yield 'ampersand' => ['a & b', 'a &amp; b'];
        yield 'already encoded entity is encoded again' => ['&amp;', '&amp;amp;'];
        yield 'double quote' => ['say "hi"', 'say &quot;hi&quot;'];
        yield 'single quote' => ["it's", 'it&#039;s'];
        yield 'attribute break out' => ['" onmouseover="x', '&quot; onmouseover=&quot;x'];
        yield 'unicode stays' => ['äöü € 日本', 'äöü € 日本'];
        yield 'zero string' => ['0', '0'];
        yield 'int' => [42, '42'];
        yield 'negative int' => [-7, '-7'];
        yield 'float' => [1.5, '1.5'];
        yield 'true' => [true, '1'];
        yield 'false' => [false, ''];
    }

    #[DataProvider('encodeProvider')]
    public function testEncode(string|float|int|bool|null $value, string $expected): void
    {
        $this->assertSame($expected, HtmlEncoder::encode(value: $value));
    }

    public function testEncodeSubstitutesInvalidUtf8(): void
    {
        $this->assertSame("a\u{FFFD}b", HtmlEncoder::encode(value: "a\xFFb"));
    }

    /**
     * @return iterable<string, array{string|float|int|bool|null, string}>
     */
    public static function encodeKeepQuotesProvider(): iterable
    {
        yield 'null' => [null, ''];
        yield 'angle brackets' => ['<b>', '&lt;b&gt;'];
        yield 'ampersand' => ['a & b', 'a &amp; b'];
        yield 'double quote stays' => ['say "hi"', 'say "hi"'];
        yield 'single quote stays' => ["it's", "it's"];
        yield 'int' => [5, '5'];
        yield 'true' => [true, '1'];
    }

    #[DataProvider('encodeKeepQuotesProvider')]
    public function testEncodeKeepQuotes(string|float|int|bool|null $value, string $expected): void
    {
        $this->assertSame($expected, HtmlEncoder::encodeKeepQuotes(value: $value));
    }

    public function testEncodeKeepQuotesSubstitutesInvalidUtf8(): void
    {
        $this->assertSame("a\u{FFFD}b", HtmlEncoder::encodeKeepQuotes(value: "a\xFFb"));
    }
}
