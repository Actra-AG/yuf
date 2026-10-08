<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\datacheck\sanitizerTypes;

use actra\yuf\datacheck\sanitizerTypes\DomainSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DomainSanitizerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function sanitizeProvider(): iterable
    {
        yield 'already clean' => ['example.com', 'example.com'];
        yield 'lower case' => ['Example.COM', 'example.com'];
        yield 'surrounding whitespace' => ['  Example.COM ', 'example.com'];
        yield 'empty' => ['', ''];
        yield 'only whitespace' => ['  ', ''];
        yield 'https scheme and trailing slash' => ['https://www.example.com/', 'example.com'];
        yield 'upper case scheme' => ['HTTP://example.com', 'example.com'];
        yield 'other scheme' => ['ftp://example.com', 'example.com'];
        yield 'www prefix' => ['www.example.com', 'example.com'];
        yield 'only the first www prefix' => ['www.www.example.com', 'www.example.com'];
        yield 'www is no prefix without the dot' => ['wwwexample.com', 'wwwexample.com'];
        yield 'www inside of a word' => ['xwww.com', 'xwww.com'];
        yield 'www as the only label stays' => ['www.com', 'www.com'];
        yield 'spaces inside' => ['ex ample.com', 'example.com'];
        yield 'zero width space' => ["a\u{200B}b.com", 'ab.com'];
        yield 'zero width space entity' => ['a&#8203;b.com', 'ab.com'];
        yield 'only one trailing slash' => ['ftp://example.com//', 'example.com'];
        yield 'path is kept' => ['example.com/path/', 'example.com'];
        yield 'query string is removed' => ['example.com?x=1', 'example.com'];
        yield 'several question marks' => ['example.com??', 'example.com'];
        yield 'several trailing slashes are removed' => ['example.com///', 'example.com'];
        yield 'path is removed' => ['example.com/path', 'example.com'];
        yield 'port is removed' => ['example.com:8080', 'example.com'];
        yield 'scheme with port and path' => ['https://example.com:8080/a/b/', 'example.com'];
        yield 'upper case with scheme' => ['HTTPS://WWW.EXAMPLE.COM', 'example.com'];
        yield 'IDN in Unicode' => ['https://www.Müller.CH/', 'müller.ch'];
        yield 'IDN in punycode' => ['XN--MLLER-KVA.CH', 'xn--mller-kva.ch'];
        yield 'zero width space is removed before the www prefix' => ["\u{200B}www.example.com", 'example.com'];
        yield 'www in a later label is no prefix' => ['foo.www.bar', 'foo.www.bar'];
        yield 'www prefix of a single label: https' => ['https://www.ch', 'www.ch'];
        yield 'fragment is removed' => ['example.com#top', 'example.com'];
        yield 'query without path' => ['https://example.com?x=1#y', 'example.com'];
        yield 'user info after a scheme is removed' => ['https://user:secret@example.com/x', 'example.com'];
        yield 'at sign without scheme is kept as typed' => ['user@example.com', 'user@example.com'];
        yield 'at sign in the path is no user info' => ['https://example.com/a@b', 'example.com'];
        yield 'port without digits' => ['example.com:', 'example.com'];
        yield 'www prefix with port and path' => ['www.example.com:8080/x', 'example.com'];
        yield 'IPv6 address in brackets with port' => ['http://[::1]:8080/', '[::1]'];
        yield 'IPv6 address without brackets is kept' => ['2001:db8::1', '2001:db8::1'];
        yield 'www.ch with port' => ['https://www.ch:443/', 'www.ch'];
        yield 'www.ch' => ['www.ch', 'www.ch'];
        yield 'www prefix and the scheme with a single label left' => ['HTTP://WWW.CH/', 'www.ch'];
        yield 'www as the only label' => ['www.', 'www.'];
        yield 'unicode is lower cased' => ['ÄÖÜ.CH', 'äöü.ch'];
    }

    #[DataProvider('sanitizeProvider')]
    public function testSanitize(string $input, string $expectedValue): void
    {
        $this->assertSame($expectedValue, DomainSanitizer::sanitize(input: $input));
    }
}
