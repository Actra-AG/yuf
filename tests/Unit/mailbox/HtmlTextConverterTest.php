<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailbox;

use actra\yuf\mailbox\HtmlTextConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HtmlTextConverterTest extends TestCase
{
    #[DataProvider('htmlProvider')]
    public function testConvertsHtmlToReadableText(string $html, string $expected): void
    {
        $this->assertSame($expected, HtmlTextConverter::convert(html: $html));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function htmlProvider(): iterable
    {
        yield 'empty' => ['', ''];
        yield 'fragment' => ['Just <i>text</i>', 'Just text'];
        yield 'styles, scripts and comments' => [
            '<style>.a{}</style><p>Text<!-- hidden --></p><script>alert(1)</script><noscript>no</noscript>',
            'Text',
        ];
        yield 'entities' => ['<p>&lt;b&gt; &amp; &quot;x&quot; &euro; &#228;</p>', '<b> & "x" € ä'];
        yield 'paragraphs and line breaks' => ['<p>One</p><p>Two<br>Three</p>', "One\n\nTwo\nThree"];
        yield 'list items and divs' => ['<ul><li>a</li><li>b</li></ul><div>c</div><div>d</div>', "a\nb\n\nc\nd"];
        yield 'whitespace collapsed' => ["<p>  many \n\t spaces&nbsp;&nbsp;here  </p>", 'many spaces here'];
        yield 'preformatted' => ["<pre>a\nb</pre>", "a\nb"];
        yield 'meta charset is ignored' => ['<meta charset="iso-8859-1"><p>Grüsse</p>', 'Grüsse'];
        yield 'paragraph mark in text is removed' => ["<p>a\x01b</p>", 'ab'];
    }
}
