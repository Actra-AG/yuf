<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\phone;

use actra\yuf\phone\PhoneDesc;
use actra\yuf\phone\PhoneMetaData;
use actra\yuf\phone\PhoneMetaDataRepository;
use actra\yuf\phone\PhoneParseErrorEnum;
use actra\yuf\phone\PhoneParseException;
use actra\yuf\phone\PhoneParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The parser itself does not check whether the length of the number is possible for its region.
 */
final class PhoneParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function numberWithImpossibleLengthProvider(): iterable
    {
        yield 'two digits' => ['12', 41, '12'];
        yield 'seven digits' => ['0446681', 41, '446681'];
        yield 'international, one digit too long' => ['+41 4466818001', 41, '4466818001'];
        yield 'NANP, too short' => ['+1 23', 1, '23'];
        yield 'trunk prefix and one digit' => ['04', 41, '4'];
    }

    #[DataProvider('numberWithImpossibleLengthProvider')]
    public function testParseDoesNotCheckTheLength(string $input, int $countryCode, string $nationalNumber): void
    {
        $phoneNumber = $this->createParser()->parse(numberToParse: $input, defaultCountryCode: 'CH');

        $this->assertSame($countryCode, $phoneNumber->countryCode);
        $this->assertSame($nationalNumber, $phoneNumber->nationalNumber);
    }

    public function testParseThrowsWithoutDefaultRegionForANationalNumber(): void
    {
        $this->expectException(PhoneParseException::class);
        $this->expectExceptionCode(PhoneParseErrorEnum::INVALID_COUNTRY_CODE->value);

        $this->createParser()->parse(numberToParse: '12', defaultCountryCode: null);
    }

    public function testParseOfAnEmptyStringThrows(): void
    {
        $this->expectException(PhoneParseException::class);
        $this->expectExceptionCode(PhoneParseErrorEnum::EMPTY_STRING->value);

        $this->createParser()->parse(numberToParse: '', defaultCountryCode: 'CH');
    }

    public function testParseOfAnInternationalNumberIgnoresTheDefaultRegion(): void
    {
        $phoneNumber = $this->createParser()->parse(numberToParse: '+44 20 7946 0958', defaultCountryCode: 'CH');

        $this->assertSame(44, $phoneNumber->countryCode);
        $this->assertSame('2079460958', $phoneNumber->nationalNumber);
    }

    public function testParseOfAllZerosKeepsOneZero(): void
    {
        $phoneNumber = $this->createParser()->parse(numberToParse: '+41 000', defaultCountryCode: null);

        $this->assertSame('0', $phoneNumber->nationalNumber);
    }

    public function testParseKeepsTheLeadingZeroOfACountryWithoutNationalPrefix(): void
    {
        $phoneNumber = $this->createParser()->parse(numberToParse: '01441234', defaultCountryCode: 'GA');

        $this->assertSame(241, $phoneNumber->countryCode);
        $this->assertSame('1441234', $phoneNumber->nationalNumber);
        $this->assertTrue($phoneNumber->italianLeadingZero);
        $this->assertSame('01441234', $phoneNumber->getNationalSignificantNumber());
    }

    public function testNationalPrefixIsKeptIfOnlyTheOriginalNumberMatchesTheGeneralDescription(): void
    {
        // The first alternative `0` matches the start of `01` only: the original is viable by the second one.
        $phoneMetaData = $this->createMetaData(generalPattern: '0|01', nationalPrefixForParsing: '0');

        $this->assertSame('01', PhoneParser::stripNationalPrefix(number: '01', phoneMetaData: $phoneMetaData));
    }

    public function testNationalPrefixIsStrippedIfTheNumberStaysViable(): void
    {
        $phoneMetaData = $this->createMetaData(generalPattern: '0|01|1', nationalPrefixForParsing: '0');

        $this->assertSame('1', PhoneParser::stripNationalPrefix(number: '01', phoneMetaData: $phoneMetaData));
    }

    private function createMetaData(string $generalPattern, string $nationalPrefixForParsing): PhoneMetaData
    {
        $general = new PhoneDesc(
            nationalNumberPattern: $generalPattern,
            possibleLength: [],
            possibleLengthLocalOnly: [],
        );
        $none = new PhoneDesc(nationalNumberPattern: '', possibleLength: [], possibleLengthLocalOnly: []);

        return new PhoneMetaData(
            countryCode: 999,
            internationalPrefix: '00',
            generalDesc: $general,
            leadingDigits: null,
            sameMobileAndFixedLinePattern: false,
            fixedLine: $none,
            mobile: $none,
            tollFree: $none,
            premiumRate: $none,
            sharedCost: $none,
            voip: $none,
            personalNumber: $none,
            pager: $none,
            uan: $none,
            voicemail: $none,
            nationalPrefixForParsing: $nationalPrefixForParsing,
            nationalPrefixTransformRule: null,
            preferredExtnPrefix: null,
            intlNumberFormats: [],
            numberFormats: [],
        );
    }

    private function createParser(): PhoneParser
    {
        return new PhoneParser(metaDataRepository: new PhoneMetaDataRepository());
    }
}
