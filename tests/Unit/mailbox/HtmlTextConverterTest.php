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
        yield 'link keeps its target' => [
            '<p>Found <a href="https://example.com/x">on a site</a>.</p>',
            'Found on a site <https://example.com/x>.',
        ];
        yield 'link with nested markup' => [
            '<a href="http://example.com/x"><b>on</b> <span>a <i>site</i></span> </a>next',
            'on a site <http://example.com/x> next',
        ];
        yield 'link target with upper case scheme' => [
            '<a href="HTTPS://example.com/X">site</a>',
            'site <HTTPS://example.com/X>',
        ];
        yield 'link target entities decoded and whitespace removed' => [
            "<a href=\" https://example.com/list?a=1&amp;b=2\n/id/23 \">site</a>",
            'site <https://example.com/list?a=1&b=2/id/23>',
        ];
        yield 'mailto link' => ['<a href="mailto:a@example.com">Mail</a>', 'Mail'];
        yield 'tel link' => ['<a href="tel:+41441234567">Call</a>', 'Call'];
        yield 'javascript link' => ['<a href="javascript:alert(1)">Run</a>', 'Run'];
        yield 'anchor link' => ['<a href="#top">Top</a>', 'Top'];
        yield 'relative link' => ['<a href="/path/page.html">Page</a>', 'Page'];
        yield 'protocol-relative link' => ['<a href="//example.com/x">Page</a>', 'Page'];
        yield 'empty href' => ['<a href="">Empty</a>', 'Empty'];
        yield 'scheme without host' => ['<a href="https://">Empty</a>', 'Empty'];
        yield 'link without href' => ['<a name="x">Name</a>', 'Name'];
        yield 'link without text' => [
            '<p>Follow us <a href="https://example.com/social"><img src="icon.png" alt="Icon"></a></p>',
            'Follow us',
        ];
        yield 'link with whitespace only' => ['<p>a<a href="https://example.com/x"> &nbsp; </a>b</p>', 'a b'];
        yield 'link text is the url' => [
            '<a href="https://example.com/x">https://example.com/x</a>',
            'https://example.com/x',
        ];
        yield 'link text is the url without scheme, trailing slash and case' => [
            '<a href="https://Example.com/">example.COM</a>',
            'example.COM',
        ];
        yield 'link text is another url' => [
            '<a href="https://example.com/track?u=1">https://example.com/</a>',
            'https://example.com/ <https://example.com/track?u=1>',
        ];
        yield 'link with block content' => [
            '<a href="https://example.com/x"><div>Open</div></a><div>Next</div>',
            "Open <https://example.com/x>\nNext",
        ];
        yield 'link in table cell' => [
            '<table><tr><td>Site</td><td><a href="https://example.com/id/23">vesper</a></td></tr></table>',
            'Site vesper <https://example.com/id/23>',
        ];
        yield 'link in preformatted text' => [
            "<pre>See\n<a href=\"https://example.com/x\">  the  site</a>\nend</pre>",
            "See\nthe site <https://example.com/x>\nend",
        ];
        yield 'mailto link in preformatted text' => ['<pre><a href="mailto:a@example.com">Mail</a></pre>', 'Mail'];
    }
}
