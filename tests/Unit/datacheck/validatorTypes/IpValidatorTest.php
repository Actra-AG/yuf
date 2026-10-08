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
        yield 'IPv4 as any IP' => ['192.168.1.1', IpTypeEnum::IP, true];
        yield 'IPv6 as any IP' => ['2001:db8::1', IpTypeEnum::IP, true];
        yield 'IPv4 as IPv4' => ['192.168.1.1', IpTypeEnum::IPV4, true];
        yield 'IPv6 as IPv4' => ['2001:db8::1', IpTypeEnum::IPV4, false];
        yield 'IPv6 as IPv6' => ['2001:db8::1', IpTypeEnum::IPV6, true];
        yield 'IPv4 as IPv6' => ['192.168.1.1', IpTypeEnum::IPV6, false];
        yield 'out of range' => ['256.1.1.1', IpTypeEnum::IP, false];
        yield 'empty string' => ['', IpTypeEnum::IP, false];
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
        yield 'empty address is not in a whitelist with an empty entry' => [[''], '', false];
        yield 'invalid address is not in a whitelist with the same entry' => [['garbage'], 'garbage', false];
        yield 'address with a leading space is not in a whitelist with the same entry' => [
            [' 10.0.0.1'],
            ' 10.0.0.1',
            false,
        ];
        yield 'zero is not an address' => [['0'], '0', false];
        yield 'address with a zone is not an address' => [['fe80::1%eth0'], 'fe80::1%eth0', false];
        yield 'IPv6 in other letter case' => [['2001:DB8::1'], '2001:db8::1', true];
        yield 'IPv6 range with other letter case' => [['2001:db8::/32'], '2001:DB8::1', true];
        yield 'IPv6 loopback range' => [['::1/128'], '::1', true];
        yield 'all IPv6 addresses' => [['::/0'], '::1', true];
        yield 'IPv6 range of a single address pair' => [['2001:db8::1/127'], '2001:db8::', true];
        yield 'IPv6 beyond range of a single address pair' => [['2001:db8::1/127'], '2001:db8::2', false];
        yield 'IPv6 /48' => [['2001:db8::/48'], '2001:db8:1::1', false];
        yield 'IPv6 /64 other subnet' => [['2001:db8::/64'], '2001:db8:0:1::1', false];
        yield 'IPv4-mapped IPv6 address in an IPv4 range' => [['10.0.0.0/8'], '::ffff:10.0.0.1', true];
        yield 'IPv4-mapped IPv6 address outside an IPv4 range' => [['10.0.0.0/8'], '::ffff:11.0.0.1', false];
        yield 'IPv4-mapped IPv6 address in the mapped range' => [['::ffff:0:0/96'], '::ffff:10.0.0.1', true];
        yield 'IPv4-mapped IPv6 address of an IPv4 address' => [['10.0.0.1'], '::ffff:10.0.0.1', true];
        yield 'IPv4-mapped IPv6 address in hex form of an IPv4 address' => [['192.0.2.1'], '::ffff:c000:201', true];
        yield 'IPv4-mapped IPv6 address in hex form in an IPv4 range' => [['192.0.2.0/24'], '::ffff:c000:0201', true];
        yield 'IPv4-mapped IPv6 address in long form' => [['192.0.2.1'], '0:0:0:0:0:ffff:192.0.2.1', true];
        yield 'IPv4-mapped IPv6 address in upper case' => [['192.0.2.1'], '::FFFF:192.0.2.1', true];
        yield 'IPv4-mapped IPv6 address of another IPv4 address' => [['192.0.2.1'], '::ffff:192.0.2.2', false];
        yield 'IPv4-mapped IPv6 address, last of an IPv4 range' => [['192.0.2.0/25'], '::ffff:192.0.2.127', true];
        yield 'IPv4-mapped IPv6 address, after an IPv4 range' => [['192.0.2.0/25'], '::ffff:192.0.2.128', false];
        yield 'IPv4-mapped IPv6 address in all IPv4 addresses' => [['0.0.0.0/0'], '::ffff:192.0.2.1', true];
        yield 'IPv4 address of a mapped entry' => [['::ffff:192.0.2.1'], '192.0.2.1', true];
        yield 'IPv4 address of a mapped entry in hex form' => [['::ffff:c000:201'], '192.0.2.1', true];
        yield 'other IPv4 address of a mapped entry' => [['::ffff:192.0.2.1'], '192.0.2.2', false];
        yield 'IPv4 address in a mapped range' => [['::ffff:192.0.2.0/120'], '192.0.2.200', true];
        yield 'IPv4 address after a mapped range' => [['::ffff:192.0.2.0/120'], '192.0.3.1', false];
        yield 'IPv4 address in the whole mapped range' => [['::ffff:0:0/96'], '203.0.113.9', true];
        yield 'IPv4 address in a mapped range with bits inside a byte' => [['::ffff:10.0.0.0/108'], '10.15.0.1', true];
        yield 'IPv4 address after a mapped range with bits in a byte' => [['::ffff:10.0.0.0/108'], '10.16.0.1', false];
        yield 'mapped address of a mapped single address range' => [['::ffff:192.0.2.1/128'], '::ffff:192.0.2.1', true];
        yield 'IPv4 address of a mapped single address range' => [['::ffff:192.0.2.1/128'], '192.0.2.1', true];
        yield 'mapped address in the IPv6 range of everything' => [['::/0'], '::ffff:192.0.2.1', true];
        yield 'mapped address in a short IPv6 range' => [['::/80'], '::ffff:192.0.2.1', true];
        yield 'IPv4 address is not in a short range of a mapped network' => [['::ffff:0:0/80'], '192.0.2.1', false];
        yield 'IPv4-compatible IPv6 address is no IPv4 address' => [['192.0.2.1'], '::192.0.2.1', false];
        yield 'IPv4-compatible IPv6 address in an IPv4 range' => [['192.0.2.0/24'], '::c000:201', false];
        yield 'IPv4 address is not an IPv4-compatible entry' => [['::192.0.2.1'], '192.0.2.1', false];
        yield 'IPv4 address is not an IPv4-compatible range' => [['::c000:200/120'], '192.0.2.1', false];
        yield 'NAT64 IPv6 address is no IPv4 address' => [['192.0.2.1'], '64:ff9b::192.0.2.1', false];
        yield 'NAT64 IPv6 address in an IPv4 range' => [['192.0.2.0/24'], '64:ff9b::c000:201', false];
        yield 'IPv4 address is not in the NAT64 entry' => [['64:ff9b::192.0.2.1'], '192.0.2.1', false];
        yield 'IPv4 address is not in the NAT64 range' => [['64:ff9b::/96'], '192.0.2.1', false];
        yield 'NAT64 address in the NAT64 range' => [['64:ff9b::/96'], '64:ff9b::192.0.2.1', true];
        yield 'IPv4-translated IPv6 address is no IPv4 address' => [['192.0.2.1'], '::ffff:0:c000:201', false];
        yield 'IPv6 address with ffff elsewhere is no IPv4 address' => [['192.0.2.1'], '1::ffff:c000:201', false];
        yield 'IPv6 address with ffff in the wrong group is no IPv4 address' => [['0.0.0.1'], '::ffff:1:0:1', false];
        yield 'leading zero of the prefix length' => [['10.0.0.0/08'], '10.1.1.1', true];
        yield 'two leading zeros of the prefix length' => [['10.0.0.0/008'], '10.1.1.1', true];
        yield 'range of two addresses, first' => [['192.0.2.0/25'], '192.0.2.127', true];
        yield 'range of two addresses, after' => [['192.0.2.0/25'], '192.0.2.128', false];
        yield 'private range, last address' => [['172.16.0.0/12'], '172.31.255.255', true];
        yield 'private range, after' => [['172.16.0.0/12'], '172.32.0.1', false];
        yield 'shortened range with three parts' => [['1.2.3/24'], '1.2.3.200', true];
        yield 'shortened range, other network' => [['10.1/16'], '10.2.0.1', false];
        yield 'shortened single part range' => [['1/8'], '1.2.3.4', true];
        yield 'address with leading zero never matches' => [['10.0.0.1'], '010.0.0.1', false];
        yield 'whitelist item with leading zero never matches' => [['01.2.3.4'], '1.2.3.4', false];
        yield 'trailing space of the address' => [['10.0.0.1'], '10.0.0.1 ', false];
        yield 'invalid plain item is ignored' => [['not an address', '10.0.0.1'], '10.0.0.1', true];
        yield 'duplicate items' => [['10.0.0.0/8', '10.0.0.0/8'], '11.0.0.1', false];
        yield 'an invalid range after a match is not read' => [['10.0.0.0/8', 'bad/range'], '10.0.0.1', true];
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
        yield 'four digit prefix length' => ['10.0.0.0/0008'];
        yield 'space after the prefix length' => ['10.0.0.0/8 '];
        yield 'negative prefix length' => ['10.0.0.0/-1'];
        yield 'text after the prefix length' => ['1.2.3.4/24x'];
        yield 'two slashes' => ['1.2.3.4/24/1'];
        yield 'empty label in the network' => ['10..0/8'];
        yield 'five parts' => ['1.2.3.4.5/8'];
        yield 'text as network' => ['bad/range'];
        yield 'IPv4 network with an IPv6 prefix length' => ['10.0.0.0/64'];
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
