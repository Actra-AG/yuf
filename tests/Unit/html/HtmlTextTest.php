<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\html;

use actra\yuf\html\HtmlText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HtmlTextTest extends TestCase
{
    public function testTextIsEscaped(): void
    {
        $this->assertSame('&lt;b&gt;x&lt;/b&gt;', HtmlText::fromText(text: '<b>x</b>')->render());
    }

    public function testHtmlIsOutputAsItIs(): void
    {
        $this->assertSame('<b>x</b>', HtmlText::fromHtml(html: '<b>x</b>')->render());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function textWithLineBreaksProvider(): array
    {
        return [
            'empty' => ['', ''],
            'escaped' => ['<b>"x" & \'y\'</b>', '&lt;b&gt;&quot;x&quot; &amp; &#039;y&#039;&lt;/b&gt;'],
            'LF' => ["a\nb", "a<br>\nb"],
            'CRLF' => ["a\r\nb", "a<br>\r\nb"],
            'CR' => ["a\rb", "a<br>\rb"],
            'HTML line break stays text' => ['a<br>b', 'a&lt;br&gt;b'],
        ];
    }

    #[DataProvider('textWithLineBreaksProvider')]
    public function testTextWithLineBreaksIsEscapedAndBreaksBecomeBr(string $text, string $expected): void
    {
        $this->assertSame($expected, HtmlText::fromTextWithLineBreaks(text: $text)->render());
    }
}
