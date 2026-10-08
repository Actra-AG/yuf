<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\phone;

use actra\yuf\phone\PhoneRegionCountryCodeMap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneRegionCountryCodeMapTest extends TestCase
{
    public function testSupportedRegionsAreTwoLetterUpperCaseCodes(): void
    {
        $regions = PhoneRegionCountryCodeMap::getSupportedRegions();

        $this->assertCount(245, $regions);
        $this->assertCount(245, array_unique(array: $regions));
        foreach ($regions as $region) {
            $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $region);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function supportedRegionProvider(): iterable
    {
        foreach (['CH', 'DE', 'AT', 'FR', 'IT', 'LI', 'US', 'GB', 'CA', 'VA', 'XK', 'AC', 'TA'] as $region) {
            yield $region => [$region];
        }
    }

    #[DataProvider('supportedRegionProvider')]
    public function testSupportedRegionsContain(string $region): void
    {
        $this->assertContains($region, PhoneRegionCountryCodeMap::getSupportedRegions());
    }

    public function testSupportedRegionsDoNotContainTheNonGeographicalEntity(): void
    {
        $this->assertNotContains('001', PhoneRegionCountryCodeMap::getSupportedRegions());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function regionCodeProvider(): iterable
    {
        yield 'Switzerland' => [41, 'CH'];
        yield 'Germany' => [49, 'DE'];
        yield 'Austria' => [43, 'AT'];
        yield 'France' => [33, 'FR'];
        yield 'Italy' => [39, 'IT'];
        yield 'Liechtenstein' => [423, 'LI'];
        yield 'NANP: the main country is first' => [1, 'US'];
        yield 'Great Britain: the main country is first' => [44, 'GB'];
        yield 'Russia and Kazakhstan' => [7, 'RU'];
        yield 'international freephone' => [800, '001'];
        yield 'international premium rate' => [979, '001'];
        yield 'satellite network' => [882, '001'];
        yield 'unknown' => [999, 'ZZ'];
        yield 'zero' => [0, 'ZZ'];
        yield 'negative' => [-1, 'ZZ'];
    }

    #[DataProvider('regionCodeProvider')]
    public function testGetRegionCodeForCountryCode(int $countryCode, string $expected): void
    {
        $regionCode = PhoneRegionCountryCodeMap::getRegionCodeForCountryCode(countryCallingCode: $countryCode);

        $this->assertSame($expected, $regionCode);
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function countryCodeProvider(): iterable
    {
        yield 'Switzerland' => [41, true];
        yield 'NANP' => [1, true];
        yield 'three digits' => [423, true];
        yield 'non-geographical' => [800, true];
        yield 'unknown' => [999, false];
        yield 'zero' => [0, false];
        yield 'negative' => [-41, false];
        yield 'too long' => [4100, false];
    }

    #[DataProvider('countryCodeProvider')]
    public function testCountryCodeExists(int $countryCode, bool $expected): void
    {
        $this->assertSame($expected, PhoneRegionCountryCodeMap::countryCodeExists(countryCodeToCheck: $countryCode));
    }

    public function testEveryRegionHasMetadataOfItsCountryCode(): void
    {
        foreach (PhoneRegionCountryCodeMap::getSupportedRegions() as $region) {
            $countryCode = PhoneExampleNumbers::countryCode(region: $region);
            $this->assertTrue(PhoneRegionCountryCodeMap::countryCodeExists(countryCodeToCheck: $countryCode), $region);
        }
    }
}
