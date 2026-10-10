<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\html;

use actra\yuf\html\HtmlSanitizer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HtmlSanitizerTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function defaultAllowlistProvider(): array
    {
        return [
            'empty' => ['', ''],
            'plain text' => ['Tom & Jerry', 'Tom &amp; Jerry'],
            'allowed tags kept' => [
                '<p>a<br><strong>b</strong> <b>c</b> <em>d</em> <i>e</i> H<sub>2</sub>O m<sup>2</sup></p>',
                '<p>a<br><strong>b</strong> <b>c</b> <em>d</em> <i>e</i> H<sub>2</sub>O m<sup>2</sup></p>',
            ],
            'lists' => ['<ul><li>a</li></ul><ol><li>b</li></ol>', '<ul><li>a</li></ul><ol><li>b</li></ol>'],
            'table with colspan and rowspan' => [
                '<table><thead><tr><th colspan="2" class="x">a</th></tr></thead>'
                . '<tbody><tr><td rowspan="2" width="10">b</td></tr></tbody></table>',
                '<table><thead><tr><th colspan="2">a</th></tr></thead>'
                . '<tbody><tr><td rowspan="2">b</td></tr></tbody></table>',
            ],
            'table without tbody gets one' => ['<table><tr><td>a</td></tr></table>', '<table><tbody><tr><td>a</td></tr></tbody></table>'],
            'disallowed element unwrapped' => ['<div><span>a</span> <u>b</u></div>', 'a b'],
            'nested disallowed element unwrapped' => [
                '<p><span><font><strong>a</strong></font></span></p>',
                '<p><strong>a</strong></p>',
            ],
            'attributes dropped' => [
                '<p class="x" id="y" title="z" data-a="1" style="color:red">a</p>',
                '<p>a</p>',
            ],
            'comment removed' => ['a<!-- <script>alert(1)</script> -->b', 'ab'],
            'entities decoded and escaped' => ['&lt;b&gt;&quot;&#39;&amp;&eacute;', '&lt;b&gt;"\'&amp;é'],
            'unclosed tags closed' => ['<p><strong>a<p>b', '<p><strong>a</strong></p><p><strong>b</strong></p>'],
            'stray end tags ignored' => ['a</p></div>b', 'a<p></p>b'],
            'misnested tags repaired' => ['<b><i>a</b>c</i>', '<b><i>a</i></b><i>c</i>'],
            'unwrapped li in li parsed as siblings' => [
                '<ul><li>a<section><li>b</li></section></li></ul>',
                '<ul><li>a</li><li>b</li></ul>',
            ],
            'unclosed tag swallowing text' => ['<p>a<div x="', '<p>a</p>'],
            'text of raw text element kept escaped' => ['<title><img src=x onerror=alert(1)></title>', '&lt;img src=x onerror=alert(1)&gt;'],
            'textarea content kept as text' => [
                '<textarea><script>alert(1)</script></textarea>',
                '&lt;script&gt;alert(1)&lt;/script&gt;',
            ],
        ];
    }

    #[DataProvider('defaultAllowlistProvider')]
    public function testDefaultAllowlist(string $html, string $expected): void
    {
        $this->assertSame(
            $expected,
            HtmlSanitizer::sanitize(html: $html, allowedTags: HtmlSanitizer::DEFAULT_ALLOWED_TAGS),
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function xssProvider(): array
    {
        return [
            'script removed with content' => ['a<script>alert(1)</script>b', 'ab'],
            'script with attributes' => ['<script src="https://evil.example/x.js"></script>a', 'a'],
            'uppercase script' => ['<SCRIPT>alert(1)</SCRIPT>a', 'a'],
            'unclosed script' => ['a<script>alert(1)', 'a'],
            'style removed with content' => ['<style>body{display:none}</style>a', 'a'],
            'template removed with content' => ['<template><img src=x onerror=alert(1)></template>a', 'a'],
            'onerror' => ['<img src=x onerror=alert(1)>a', 'a'],
            'onclick on allowed tag' => ['<p onclick="alert(1)">a</p>', '<p>a</p>'],
            'event handler on td' => ['<table><tr><td onmouseover="alert(1)" colspan="2">a</td></tr></table>', '<table><tbody><tr><td colspan="2">a</td></tr></tbody></table>'],
            'javascript URL' => ['<a href="javascript:alert(1)">a</a>', 'a'],
            'iframe' => ['<iframe src="javascript:alert(1)"></iframe>a', 'a'],
            'svg with onload' => ['<svg onload=alert(1)><circle r="1"/></svg>a', 'a'],
            'svg script' => ['<svg><script>alert(1)</script></svg>a', 'a'],
            'svg style' => ['<svg><style>*{color:red}</style>b</svg>', 'b'],
            'allowed name in svg unwrapped' => ['<svg><p>a</p></svg>', '<p>a</p>'],
            'math with mglyph' => [
                '<math><mtext><table><mglyph><style><img src=x onerror=alert(1)>',
                '<table></table>',
            ],
            'encoded script stays text' => ['&lt;script&gt;alert(1)&lt;/script&gt;', '&lt;script&gt;alert(1)&lt;/script&gt;'],
            'nested to rebuild script' => ['<scr<script>x</script>ipt>alert(1)</script>', 'xipt&gt;alert(1)'],
            'object and embed' => ['<object data="x.swf"><embed src="x.swf"></object>a', 'a'],
            'form with action' => ['<form action="javascript:alert(1)"><button formaction="javascript:alert(1)">a</button></form>', 'a'],
            'meta refresh' => ['<meta http-equiv="refresh" content="0;url=javascript:alert(1)">a', 'a'],
            'base' => ['<base href="https://evil.example/">a', 'a'],
            'null byte in tag' => ["<scr\0ipt>alert(1)</scr\0ipt>", 'alert(1)'],
            'attribute breaking out' => ['<p title="a&quot;onclick=alert(1)//">b</p>', '<p>b</p>'],
        ];
    }

    #[DataProvider('xssProvider')]
    public function testXssVectorsAreRemoved(string $html, string $expected): void
    {
        $this->assertSame(
            $expected,
            HtmlSanitizer::sanitize(html: $html, allowedTags: HtmlSanitizer::DEFAULT_ALLOWED_TAGS),
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function urlProvider(): array
    {
        return [
            'https' => ['<a href="https://example.com/?a=1&amp;b=2">x</a>', '<a href="https://example.com/?a=1&amp;b=2">x</a>'],
            'http' => ['<a href="http://example.com">x</a>', '<a href="http://example.com">x</a>'],
            'mailto' => ['<a href="mailto:info@example.com">x</a>', '<a href="mailto:info@example.com">x</a>'],
            'tel' => ['<a href="tel:+41441234567">x</a>', '<a href="tel:+41441234567">x</a>'],
            'relative' => ['<a href="/products/1?x=a:b#c">x</a>', '<a href="/products/1?x=a:b#c">x</a>'],
            'javascript' => ['<a href="javascript:alert(1)">x</a>', '<a>x</a>'],
            'javascript uppercase' => ['<a href="JaVaScRiPt:alert(1)">x</a>', '<a>x</a>'],
            'javascript with entities' => ['<a href="&#106;avascript&#58;alert(1)">x</a>', '<a>x</a>'],
            'javascript with tab' => ["<a href=\"java\tscript:alert(1)\">x</a>", '<a>x</a>'],
            'javascript with leading space' => ['<a href=" &#14; javascript:alert(1)">x</a>', '<a>x</a>'],
            'data' => ['<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">x</a>', '<a>x</a>'],
            'vbscript' => ['<a href="vbscript:msgbox(1)">x</a>', '<a>x</a>'],
            'invalid scheme' => ['<a href="1x:alert(1)">x</a>', '<a>x</a>'],
            'title kept' => ['<a href="javascript:alert(1)" title="t">x</a>', '<a title="t">x</a>'],
        ];
    }

    #[DataProvider('urlProvider')]
    public function testUrlsAreKeptOnlyForAllowedSchemes(string $html, string $expected): void
    {
        $this->assertSame(
            $expected,
            HtmlSanitizer::sanitize(html: $html, allowedTags: ['a' => ['href', 'title']]),
        );
    }

    public function testDefaultAllowlistDropsLinks(): void
    {
        $this->assertSame(
            'a',
            HtmlSanitizer::sanitize(html: '<a href="https://example.com">a</a>', allowedTags: HtmlSanitizer::DEFAULT_ALLOWED_TAGS),
        );
    }

    public function testEmptyAllowlistKeepsTextOnly(): void
    {
        $this->assertSame(
            'a b &lt;c&gt;',
            HtmlSanitizer::sanitize(html: '<p>a <strong>b</strong> &lt;c&gt;</p><script>x</script>', allowedTags: []),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function idempotentProvider(): array
    {
        $cases = [];
        foreach ([...self::defaultAllowlistProvider(), ...self::xssProvider()] as $name => [$html]) {
            $cases[$name] = [$html];
        }

        return $cases;
    }

    #[DataProvider('idempotentProvider')]
    public function testSanitizingIsIdempotent(string $html): void
    {
        $once = HtmlSanitizer::sanitize(html: $html, allowedTags: HtmlSanitizer::DEFAULT_ALLOWED_TAGS);

        $this->assertSame($once, HtmlSanitizer::sanitize(html: $once, allowedTags: HtmlSanitizer::DEFAULT_ALLOWED_TAGS));
    }

    /**
     * @return array<string, array{array<string, list<string>>}>
     */
    public static function invalidAllowlistProvider(): array
    {
        return [
            'script' => [['script' => []]],
            'style tag' => [['style' => []]],
            'template' => [['template' => []]],
            'style attribute' => [['p' => ['style']]],
            'event handler' => [['img' => ['onerror']]],
            'srcset' => [['img' => ['srcset']]],
            'uppercase tag' => [['P' => []]],
            'uppercase attribute' => [['td' => ['COLSPAN']]],
            'namespaced attribute' => [['a' => ['xlink:href']]],
        ];
    }

    /**
     * @param array<string, list<string>> $allowedTags
     */
    #[DataProvider('invalidAllowlistProvider')]
    public function testInvalidAllowlistThrows(array $allowedTags): void
    {
        $this->expectException(InvalidArgumentException::class);

        HtmlSanitizer::sanitize(html: 'a', allowedTags: $allowedTags);
    }
}
