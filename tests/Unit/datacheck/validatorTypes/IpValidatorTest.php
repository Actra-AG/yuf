<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\datacheck\validatorTypes;

use actra\yuf\datacheck\validatorTypes\IpTypeEnum;
use actra\yuf\datacheck\validatorTypes\IpValidator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IpValidatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, IpTypeEnum, bool}>
     */
    public static function validateProvider(): iterable
    {
        yield 'IPv4 as any IP' => ['192.168.1.1', IpTypeEnum::ip, true];
        yield 'IPv6 as any IP' => ['2001:db8::1', IpTypeEnum::ip, true];
        yield 'IPv4 as IPv4' => ['192.168.1.1', IpTypeEnum::ipv4, true];
        yield 'IPv6 as IPv4' => ['2001:db8::1', IpTypeEnum::ipv4, false];
        yield 'IPv6 as IPv6' => ['2001:db8::1', IpTypeEnum::ipv6, true];
        yield 'IPv4 as IPv6' => ['192.168.1.1', IpTypeEnum::ipv6, false];
        yield 'out of range' => ['256.1.1.1', IpTypeEnum::ip, false];
        yield 'empty string' => ['', IpTypeEnum::ip, false];
    }

    #[DataProvider('validateProvider')]
    public function testValidate(string $input, IpTypeEnum $ipType, bool $expectedResult): void
    {
        $this->assertSame($expectedResult, IpValidator::validate(input: $input, ipType: $ipType));
    }

    /**
     * @return iterable<string, array{list<string>, string, bool}>
     */
    public static function isInWhitelistProvider(): iterable
    {
        yield 'exact match' => [['10.0.0.1'], '10.0.0.1', true];
        yield 'no match' => [['10.0.0.1'], '10.0.0.2', false];
        yield 'empty whitelist' => [[], '10.0.0.1', false];
        yield 'first address of range' => [['192.168.1.0/24'], '192.168.1.0', true];
        yield 'last address of range' => [['192.168.1.0/24'], '192.168.1.255', true];
        yield 'address after range' => [['192.168.1.0/24'], '192.168.2.0', false];
        yield 'shortened range notation' => [['10/8'], '10.200.3.4', true];
        yield 'match in second entry' => [['10.0.0.1', '172.16.0.0/12'], '172.31.255.255', true];
        yield 'shortened range with two parts' => [['10.1/16'], '10.1.200.3', true];
        yield 'address before range' => [['192.168.1.0/24'], '192.168.0.255', false];
        yield 'range not starting at the network address' => [['192.168.1.77/24'], '192.168.1.5', true];
        yield 'beyond the network of a range not starting at the network address' => [
            ['192.168.1.77/24'],
            '192.168.2.50',
            false,
        ];
        yield 'range with bits inside a byte' => [['10.0.0.0/12'], '10.15.255.255', true];
        yield 'after range with bits inside a byte' => [['10.0.0.0/12'], '10.16.0.0', false];
        yield 'single address range' => [['10.0.0.1/32'], '10.0.0.1', true];
        yield 'all IPv4 addresses' => [['0.0.0.0/0'], '203.0.113.9', true];
        yield 'IPv6 address' => [['2001:db8::1'], '2001:db8::1', true];
        yield 'IPv6 address in other notation' => [['2001:0db8:0000::0001'], '2001:db8::1', true];
        yield 'IPv6 in range' => [['2001:db8::/32'], '2001:db8:ffff::1', true];
        yield 'IPv6 outside range' => [['2001:db8::/32'], '2a00:1450:4001::1', false];
        yield 'IPv6 loopback outside range' => [['2001:db8::/32'], '::1', false];
        yield 'IPv6 with bits inside a byte' => [['2001:db8::/31'], '2001:db9::1', true];
        yield 'IPv6 after range with bits inside a byte' => [['2001:db8::/31'], '2001:dba::1', false];
        yield 'IPv4 address and IPv6 range' => [['::/0'], '10.0.0.1', false];
        yield 'IPv6 address and IPv4 range' => [['0.0.0.0/0'], '2001:db8::1', false];
        yield 'empty address' => [['10.0.0.0/8'], '', false];
        yield 'invalid address' => [['10.0.0.0/8'], '10.0.0.300', false];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRangeProvider(): iterable
    {
        yield 'mask not numeric' => ['10.0.0.0/abc'];
        yield 'empty mask' => ['10.0.0.0/'];
        yield 'IPv4 mask too large' => ['10.0.0.0/33'];
        yield 'IPv6 mask too large' => ['2001:db8::/129'];
        yield 'invalid network' => ['10.0.0.300/8'];
        yield 'no network' => ['/8'];
    }

    #[DataProvider('invalidRangeProvider')]
    public function testIsInWhitelistThrowsForInvalidRange(string $range): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid IP range in the whitelist: ' . $range);

        IpValidator::isInWhitelist(whiteList: [$range], ipAddressToCheck: '10.0.0.1');
    }

    /**
     * @param list<string> $whiteList
     */
    #[DataProvider('isInWhitelistProvider')]
    public function testIsInWhitelist(array $whiteList, string $ipAddressToCheck, bool $expectedResult): void
    {
        $this->assertSame(
            $expectedResult,
            IpValidator::isInWhitelist(whiteList: $whiteList, ipAddressToCheck: $ipAddressToCheck),
        );
    }
}
