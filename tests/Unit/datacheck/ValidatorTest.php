<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\datacheck;

use actra\yuf\datacheck\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function stringWithoutWhitespacesProvider(): iterable
    {
        yield 'text' => ['abc', true];
        yield 'empty' => ['', true];
        yield 'space' => ['a b', false];
        yield 'tab' => ["a\tb", false];
        yield 'line break' => ["a\nb", false];
        yield 'trailing line break' => ["ab\n", false];
        yield 'carriage return' => ["a\rb", false];
        yield 'vertical tab' => ["a\u{0B}b", false];
        yield 'form feed' => ["a\u{0C}b", false];
        yield 'only whitespace' => [' ', false];
    }

    #[DataProvider('stringWithoutWhitespacesProvider')]
    public function testStringWithoutWhitespaces(string $input, bool $expectedResult): void
    {
        $this->assertSame($expectedResult, Validator::stringWithoutWhitespaces(input: $input));
    }

    public function testDomainDelegatesToTheDomainValidator(): void
    {
        $this->assertTrue(Validator::domain(input: 'example.com'));
        $this->assertFalse(Validator::domain(input: 'example.invalid'));
    }

    public function testTldDelegatesToTheTldValidator(): void
    {
        $this->assertTrue(Validator::tld(input: 'ch'));
        $this->assertFalse(Validator::tld(input: 'invalid'));
    }

    /**
     * @return iterable<string, array{string, bool, bool, bool}>
     */
    public static function ipProvider(): iterable
    {
        yield 'IPv4' => ['192.0.2.1', true, true, false];
        yield 'IPv6' => ['2001:db8::1', true, false, true];
        yield 'text' => ['example.com', false, false, false];
        yield 'empty' => ['', false, false, false];
        yield 'IPv4 with port' => ['192.0.2.1:80', false, false, false];
        yield 'IPv4 with a leading zero' => ['192.0.2.01', false, false, false];
        yield 'IPv6 with zone' => ['fe80::1%eth0', false, false, false];
        yield 'IPv4 range' => ['192.0.2.0/24', false, false, false];
    }

    #[DataProvider('ipProvider')]
    public function testIp(string $input, bool $isIp, bool $isIpv4, bool $isIpv6): void
    {
        $this->assertSame($isIp, Validator::ip(input: $input));
        $this->assertSame($isIpv4, Validator::ipv4(input: $input));
        $this->assertSame($isIpv6, Validator::ipv6(input: $input));
    }
}
