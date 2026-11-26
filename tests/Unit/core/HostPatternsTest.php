<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\HostPatterns;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class HostPatternsTest extends TestCase
{
    /**
     * @param list<string> $patterns
     */
    #[DataProvider('hostProvider')]
    public function testIsAllowed(array $patterns, string $host, bool $expected): void
    {
        $this->assertSame($expected, new HostPatterns(patterns: $patterns)->isAllowed(host: $host));
    }

    /**
     * @return array<string, array{list<string>, string, bool}>
     */
    public static function hostProvider(): array
    {
        $label63 = str_repeat(string: 'a', times: 63);
        // 251 characters: with "a." the name has the maximum of 253 characters
        $baseName = str_repeat(string: 'b', times: 63) . '.' . str_repeat(string: 'c', times: 63) . '.'
            . str_repeat(string: 'd', times: 63) . '.' . str_repeat(string: 'e', times: 56) . '.ch';

        return [
            'exact' => [['example.ch'], 'example.ch', true],
            'exact in upper case' => [['example.ch'], 'EXAMPLE.ch', true],
            'pattern in upper case' => [['Example.CH'], 'example.ch', true],
            'other host' => [['example.ch'], 'example.com', false],
            'single label' => [['localhost'], 'localhost', true],
            'exact with port' => [['example.ch:8443'], 'example.ch:8443', true],
            'exact without the port of the pattern' => [['example.ch:8443'], 'example.ch', false],
            'exact with a port the pattern has not' => [['example.ch'], 'example.ch:8443', false],
            'exact with trailing dot' => [['example.ch'], 'example.ch.', false],
            'IPv4' => [['127.0.0.1'], '127.0.0.1', true],
            'IPv4 with port' => [['127.0.0.1:8080'], '127.0.0.1:8080', true],
            'IPv6' => [['[::1]'], '[::1]', true],
            'IPv6 with port' => [['[::1]:8080'], '[::1]:8080', true],
            'IPv6 without the port of the pattern' => [['[::1]:8080'], '[::1]', false],
            'wildcard' => [['*.example.ch'], 'dubach.example.ch', true],
            'wildcard with hyphen' => [['*.example.ch'], 'a-b.example.ch', true],
            'wildcard in upper case' => [['*.example.ch'], 'Dubach.Example.CH', true],
            'wildcard with punycode' => [['*.example.ch'], 'xn--mller-kva.example.ch', true],
            'wildcard digits' => [['*.example.ch'], '123.example.ch', true],
            'wildcard 63 characters' => [['*.example.ch'], $label63 . '.example.ch', true],
            'wildcard 64 characters' => [['*.example.ch'], $label63 . 'a.example.ch', false],
            'wildcard name of 253 characters' => [['*.' . $baseName], 'a.' . $baseName, true],
            'wildcard name of 254 characters' => [['*.' . $baseName], 'aa.' . $baseName, false],
            'wildcard does not match the apex' => [['*.example.ch'], 'example.ch', false],
            'wildcard matches one label only' => [['*.example.ch'], 'a.b.example.ch', false],
            'wildcard empty label' => [['*.example.ch'], '.example.ch', false],
            'wildcard double dot' => [['*.example.ch'], 'a..example.ch', false],
            'wildcard leading hyphen' => [['*.example.ch'], '-a.example.ch', false],
            'wildcard trailing hyphen' => [['*.example.ch'], 'a-.example.ch', false],
            'wildcard trailing dot' => [['*.example.ch'], 'dubach.example.ch.', false],
            'wildcard suffix of another domain' => [['*.example.ch'], 'dubach.example.ch.evil.com', false],
            'wildcard without dot' => [['*.example.ch'], 'evilexample.ch', false],
            'wildcard underscore' => [['*.example.ch'], 'a_b.example.ch', false],
            'wildcard with port' => [['*.example.ch:8443'], 'x.example.ch:8443', true],
            'wildcard without the port of the pattern' => [['*.example.ch:8443'], 'x.example.ch', false],
            'wildcard with another port' => [['*.example.ch:8443'], 'x.example.ch:8444', false],
            'wildcard with a port the pattern has not' => [['*.example.ch'], 'x.example.ch:8443', false],
            'wildcard with empty port' => [['*.example.ch'], 'x.example.ch:', false],
            'wildcard with line break' => [['*.example.ch'], "a\n.example.ch", false],
            'wildcard with carriage return' => [['*.example.ch'], "a\r.example.ch", false],
            'wildcard with space' => [['*.example.ch'], 'a b.example.ch', false],
            'wildcard with null byte' => [['*.example.ch'], "a\0.example.ch", false],
            'wildcard with at sign' => [['*.example.ch'], 'user@a.example.ch', false],
            'wildcard with slash' => [['*.example.ch'], 'a/.example.ch', false],
            'wildcard with backslash' => [['*.example.ch'], 'a\\.example.ch', false],
            'wildcard with script' => [['*.example.ch'], '<script>.example.ch', false],
            'apex and wildcard' => [['example.ch', '*.example.ch'], 'example.ch', true],
            'second wildcard' => [['*.example.com', '*.example.ch'], 'a.example.ch', true],
            'empty list' => [[], 'example.ch', false],
            'empty host' => [['example.ch'], '', false],
        ];
    }

    #[DataProvider('invalidPatternProvider')]
    public function testInvalidPatternThrows(string $pattern): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The host pattern "' . $pattern . '" is invalid');

        new HostPatterns(patterns: ['example.ch', $pattern]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPatternProvider(): array
    {
        return [
            'empty' => [''],
            'space' => [' '],
            'leading space' => [' example.ch'],
            'line break' => ["example.ch\n"],
            'star' => ['*'],
            'star dot' => ['*.'],
            'top-level domain only' => ['*.com'],
            'star in label' => ['a*.example.ch'],
            'star without dot' => ['*example.ch'],
            'two stars' => ['*.*.example.ch'],
            'star inside' => ['sub.*.example.ch'],
            'star at the end' => ['example.*'],
            'star in IPv4' => ['*.0.0.1'],
            'star in IPv6' => ['*.[::1]'],
            'trailing dot' => ['example.ch.'],
            'empty label' => ['a..example.ch'],
            'leading hyphen' => ['-a.example.ch'],
            'label of 64 characters' => [str_repeat(string: 'a', times: 64) . '.ch'],
            'name of 254 characters' => [str_repeat(string: 'a.', times: 126) . 'ch'],
            'underscore' => ['a_b.example.ch'],
            'umlaut' => ['müller.ch'],
            'empty port' => ['example.ch:'],
            'wildcard with empty port' => ['*.example.ch:'],
            'port 0' => ['example.ch:0'],
            'port with leading zero' => ['example.ch:08443'],
            'port too high' => ['example.ch:65536'],
            'port with letters' => ['example.ch:abc'],
            'two ports' => ['example.ch:80:81'],
            'invalid IPv6' => ['[::g]'],
            'IPv6 without brackets' => ['::1'],
            'IPv6 without closing bracket' => ['[::1'],
            'IPv6 with text after the bracket' => ['[::1]x'],
            'scheme' => ['https://example.ch'],
            'path' => ['example.ch/'],
        ];
    }

    public function testPatternsAreKeptInLowerCase(): void
    {
        $this->assertSame(
            ['example.ch', '*.example.ch:8443'],
            new HostPatterns(patterns: ['Example.CH', '*.EXAMPLE.ch:8443'])->getPatterns(),
        );
    }

    /**
     * Random hosts from the host alphabet agree with a simple reference: one valid label in front of the base name.
     */
    public function testRandomHostsAgreeWithTheReference(): void
    {
        // Seeded, so a failure can be repeated
        $randomizer = new Randomizer(engine: new Mt19937(seed: 20261126));
        $patterns = new HostPatterns(patterns: ['*.example.ch']);
        $alphabet = 'aZ09-._:@[]/';
        for ($i = 0; $i < 1000; $i++) {
            $prefix = '';
            $length = $randomizer->getInt(min: 0, max: 8);
            for ($j = 0; $j < $length; $j++) {
                $prefix .= $alphabet[$randomizer->getInt(min: 0, max: strlen(string: $alphabet) - 1)];
            }
            $host = $prefix . '.example.ch';
            $expected = preg_match(pattern: '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D', subject: strtolower($prefix)) === 1;

            $this->assertSame($expected, $patterns->isAllowed(host: $host), $host);
        }
    }
}
