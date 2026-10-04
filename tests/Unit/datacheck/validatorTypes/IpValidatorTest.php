<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\datacheck\validatorTypes;

use actra\yuf\datacheck\validatorTypes\IpTypeEnum;
use actra\yuf\datacheck\validatorTypes\IpValidator;
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
    }

    /**
     * @param list<string> $whiteList
     */
    #[DataProvider('isInWhitelistProvider')]
    public function testIsInWhitelist(array $whiteList, string $ipAddressToCheck, bool $expectedResult): void
    {
        $this->assertSame(
            $expectedResult,
            IpValidator::isInWhitelist(whiteList: $whiteList, ipAddressToCheck: $ipAddressToCheck)
        );
    }
}
