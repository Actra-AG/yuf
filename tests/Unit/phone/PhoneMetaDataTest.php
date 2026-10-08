<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\phone;

use actra\yuf\phone\PhoneMetaDataRepository;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneMetaDataTest extends TestCase
{
    private PhoneMetaDataRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        $this->repository = new PhoneMetaDataRepository();
    }

    public function testMetadataOfSwitzerland(): void
    {
        $phoneMetaData = $this->repository->getForRegion(regionCode: 'CH');

        $this->assertNotNull($phoneMetaData);
        $this->assertSame(41, $phoneMetaData->countryCode);
        $this->assertSame('00', $phoneMetaData->internationalPrefix);
        $this->assertSame('0', $phoneMetaData->nationalPrefixForParsing);
        $this->assertNull($phoneMetaData->nationalPrefixTransformRule);
        $this->assertNull($phoneMetaData->preferredExtnPrefix);
        $this->assertFalse($phoneMetaData->hasPreferredExtnPrefix());
        $this->assertCount(3, $phoneMetaData->numberFormats);
        $this->assertCount(0, $phoneMetaData->intlNumberFormats);
    }

    public function testGeneralDescriptionOfSwitzerland(): void
    {
        $generalDesc = $this->repository->getForRegion(regionCode: 'CH')?->generalDesc;

        $this->assertNotNull($generalDesc);
        $this->assertSame('8\d{11}|[2-9]\d{8}', $generalDesc->nationalNumberPattern);
        $this->assertSame([9, 12], $generalDesc->possibleLength);
        $this->assertSame([], $generalDesc->possibleLengthLocalOnly);
    }

    public function testGeneralDescriptionWithLocalOnlyLengths(): void
    {
        $generalDesc = $this->repository->getForRegion(regionCode: 'US')?->generalDesc;

        $this->assertNotNull($generalDesc);
        $this->assertSame([10], $generalDesc->possibleLength);
        $this->assertSame([7], $generalDesc->possibleLengthLocalOnly);
    }

    public function testNumberFormatOfSwitzerland(): void
    {
        $phoneMetaData = $this->repository->getForRegion(regionCode: 'CH');

        $this->assertNotNull($phoneMetaData);
        $this->assertArrayHasKey(1, $phoneMetaData->numberFormats);
        $numberFormat = $phoneMetaData->numberFormats[1];
        $this->assertSame('(\d{2})(\d{3})(\d{2})(\d{2})', $numberFormat->pattern);
        $this->assertSame('$1 $2 $3 $4', $numberFormat->format);
        $this->assertSame(['[2-79]|81'], $numberFormat->leadingDigitsPatterns);
    }

    public function testInternationalFormatOfTheUnitedStates(): void
    {
        $phoneMetaData = $this->repository->getForRegion(regionCode: 'US');

        $this->assertNotNull($phoneMetaData);
        $this->assertCount(2, $phoneMetaData->numberFormats);
        $this->assertCount(1, $phoneMetaData->intlNumberFormats);
        $this->assertSame('$1-$2-$3', $phoneMetaData->intlNumberFormats[0]->format);
    }

    public function testNationalPrefixTransformRuleOfBrazil(): void
    {
        $phoneMetaData = $this->repository->getForRegion(regionCode: 'BR');

        $this->assertNotNull($phoneMetaData);
        $this->assertSame('$2', $phoneMetaData->nationalPrefixTransformRule);
    }

    public function testPreferredExtensionPrefixOfGreatBritain(): void
    {
        $phoneMetaData = $this->repository->getForRegion(regionCode: 'GB');

        $this->assertNotNull($phoneMetaData);
        $this->assertTrue($phoneMetaData->hasPreferredExtnPrefix());
        $this->assertSame(' x', $phoneMetaData->preferredExtnPrefix);
    }

    public function testMetadataWithoutNationalPrefix(): void
    {
        $phoneMetaData = $this->repository->getForRegion(regionCode: 'CI');

        $this->assertNotNull($phoneMetaData);
        $this->assertNull($phoneMetaData->nationalPrefixForParsing);
    }

    public function testItalyAndTheVaticanShareTheCountryCode(): void
    {
        $this->assertSame(39, $this->repository->getForRegion(regionCode: 'IT')?->countryCode);
        $this->assertSame(39, $this->repository->getForRegion(regionCode: 'VA')?->countryCode);
    }

    public function testMetadataIsTheSameObjectOnEveryCall(): void
    {
        $this->assertSame(
            $this->repository->getForRegion(regionCode: 'CH'),
            $this->repository->getForRegion(regionCode: 'CH'),
        );
    }

    public function testEveryRepositoryLoadsItsOwnMetadata(): void
    {
        $this->assertNotSame(
            $this->repository->getForRegion(regionCode: 'CH'),
            new PhoneMetaDataRepository()->getForRegion(regionCode: 'CH'),
        );
        $this->assertEquals(
            $this->repository->getForRegion(regionCode: 'CH'),
            new PhoneMetaDataRepository()->getForRegion(regionCode: 'CH'),
        );
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function unknownRegionProvider(): iterable
    {
        yield 'null' => [null];
        yield 'unknown' => ['XX'];
        yield 'lower case' => ['ch'];
        yield 'ZZ' => ['ZZ'];
        yield 'non-geographical entity' => ['001'];
        yield 'empty' => [''];
    }

    #[DataProvider('unknownRegionProvider')]
    public function testGetForRegionIsNullForAnUnknownRegion(?string $regionCode): void
    {
        $this->assertNull($this->repository->getForRegion(regionCode: $regionCode));
    }

    public function testGetForRegionOrCallingCodeUsesTheRegion(): void
    {
        $phoneMetaData = $this->repository->getForRegionOrCallingCode(countryCallingCode: 41, regionCode: 'CH');

        $this->assertSame(41, $phoneMetaData?->countryCode);
    }

    public function testGetForRegionOrCallingCodeUsesTheCallingCodeForTheNonGeographicalEntity(): void
    {
        $phoneMetaData = $this->repository->getForRegionOrCallingCode(countryCallingCode: 800, regionCode: '001');

        $this->assertNotNull($phoneMetaData);
        $this->assertSame(800, $phoneMetaData->countryCode);
        $this->assertSame('', $phoneMetaData->internationalPrefix);
        $this->assertSame('\d{8}', $phoneMetaData->generalDesc->nationalNumberPattern);
    }

    public function testGetForRegionOrCallingCodeIsNullForAnUnknownCallingCode(): void
    {
        $this->assertNull($this->repository->getForRegionOrCallingCode(countryCallingCode: 999, regionCode: '001'));
    }

    public function testGetForRegionOrCallingCodeIsNullForAnUnknownRegion(): void
    {
        $this->assertNull($this->repository->getForRegionOrCallingCode(countryCallingCode: 999, regionCode: 'ZZ'));
    }

    public function testNonGeographicalMetadataIsTheSameObjectOnEveryCall(): void
    {
        $this->assertSame(
            $this->repository->getForRegionOrCallingCode(countryCallingCode: 800, regionCode: '001'),
            $this->repository->getForRegionOrCallingCode(countryCallingCode: 800, regionCode: '001'),
        );
    }
}
