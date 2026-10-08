<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\phone;

use actra\yuf\phone\PhoneNumber;
use actra\yuf\phone\PhoneParseException;
use actra\yuf\phone\PhoneRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Parses and renders every example number of the metadata of every region.
 */
final class PhoneRegionExampleNumbersTest extends TestCase
{
    /**
     * Example numbers of the metadata that are not possible numbers of their own region (the metadata has them with a
     * trunk prefix or too short).
     */
    private const array NOT_POSSIBLE = [
        'NO uan', 'SJ uan', 'CI mobile', 'NE tollFree', 'NE premiumRate', 'CG mobile', 'SZ tollFree', 'SM fixedLine',
        'BZ tollFree', 'TO tollFree', 'FJ tollFree',
    ];

    /**
     * Example numbers that start with the trunk prefix of the region: the national number is without it.
     */
    private const array WITH_TRUNK_PREFIX = ['MX mobile', 'GA fixedLine', 'GA mobile'];

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function exampleNumberProvider(): iterable
    {
        foreach (PhoneExampleNumbers::regions() as $region) {
            foreach (PhoneExampleNumbers::listForRegion(region: $region) as $type => $example) {
                yield $region . ' ' . $type => [$region, $type, $example];
            }
        }
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function possibleExampleNumberProvider(): iterable
    {
        foreach (PhoneRegionExampleNumbersTest::exampleNumberProvider() as $name => $case) {
            if (!in_array(needle: $name, haystack: PhoneRegionExampleNumbersTest::NOT_POSSIBLE, strict: true)) {
                yield $name => $case;
            }
        }
    }

    public function testTheMetadataHasExampleNumbersForEveryRegion(): void
    {
        $this->assertCount(245, PhoneExampleNumbers::regions());
        $this->assertSame(1096, iterator_count(PhoneRegionExampleNumbersTest::exampleNumberProvider()));
    }

    #[DataProvider('possibleExampleNumberProvider')]
    public function testExampleNumberIsParsedWithTheCountryCodeOfItsRegion(
        string $region,
        string $type,
        string $example,
    ): void {
        $phoneNumber = PhoneNumber::createFromString(input: $example, defaultCountryCode: $region);

        $this->assertSame(PhoneExampleNumbers::countryCode(region: $region), $phoneNumber->countryCode);
        $this->assertSame('', $phoneNumber->extension);
    }

    #[DataProvider('possibleExampleNumberProvider')]
    public function testExampleNumberKeepsItsDigits(string $region, string $type, string $example): void
    {
        $phoneNumber = PhoneNumber::createFromString(input: $example, defaultCountryCode: $region);

        $expected = in_array(
            needle: $region . ' ' . $type,
            haystack: PhoneRegionExampleNumbersTest::WITH_TRUNK_PREFIX,
            strict: true,
        ) ? substr(string: $example, offset: 1) : $example;
        $this->assertSame($expected, $phoneNumber->getNationalSignificantNumber());
    }

    #[DataProvider('possibleExampleNumberProvider')]
    public function testExampleNumberIsRenderedInTheInternationalFormat(
        string $region,
        string $type,
        string $example,
    ): void {
        $phoneNumber = PhoneNumber::createFromString(input: $example, defaultCountryCode: $region);

        $international = PhoneRenderer::renderInternationalFormat(phoneNumber: $phoneNumber);

        $this->assertStringStartsWith(
            '+' . PhoneExampleNumbers::countryCode(region: $region) . ' ',
            $international,
        );
        $this->assertSame(
            $phoneNumber->getNationalSignificantNumber(),
            preg_replace(pattern: '/\D/', replacement: '', subject: $this->removeCallingCode($international, $region)),
        );
    }

    private function removeCallingCode(string $international, string $region): string
    {
        return substr(
            string: $international,
            offset: strlen(string: '+' . PhoneExampleNumbers::countryCode(region: $region) . ' '),
        );
    }

    #[DataProvider('possibleExampleNumberProvider')]
    public function testInternationalFormatIsParsedToTheSameNumber(string $region, string $type, string $example): void
    {
        $phoneNumber = PhoneNumber::createFromString(input: $example, defaultCountryCode: $region);

        $parsedAgain = PhoneNumber::createFromString(
            input: PhoneRenderer::renderInternationalFormat(phoneNumber: $phoneNumber),
            defaultCountryCode: null,
        );

        $this->assertSame($phoneNumber->countryCode, $parsedAgain->countryCode);
        $this->assertSame($phoneNumber->getNationalSignificantNumber(), $parsedAgain->getNationalSignificantNumber());
    }

    #[DataProvider('possibleExampleNumberProvider')]
    public function testInternalFormatIsParsedToTheSameNumber(string $region, string $type, string $example): void
    {
        $phoneNumber = PhoneNumber::createFromString(input: $example, defaultCountryCode: $region);

        $parsedAgain = PhoneNumber::createFromString(
            input: PhoneRenderer::renderInternalFormat(phoneNumber: $phoneNumber),
            defaultCountryCode: null,
        );

        $this->assertSame($phoneNumber->countryCode, $parsedAgain->countryCode);
        $this->assertSame($phoneNumber->getNationalSignificantNumber(), $parsedAgain->getNationalSignificantNumber());
    }

    #[DataProvider('possibleExampleNumberProvider')]
    public function testExampleNumberWithCallingCodeIsParsedWithoutDefaultRegion(
        string $region,
        string $type,
        string $example,
    ): void {
        $phoneNumber = PhoneNumber::createFromString(input: $example, defaultCountryCode: $region);
        $callingCode = PhoneExampleNumbers::countryCode(region: $region);

        $parsedAgain = PhoneNumber::createFromString(
            input: '+' . $callingCode . ' ' . $phoneNumber->getNationalSignificantNumber(),
            defaultCountryCode: null,
        );

        $this->assertSame($callingCode, $parsedAgain->countryCode);
        $this->assertSame($phoneNumber->getNationalSignificantNumber(), $parsedAgain->getNationalSignificantNumber());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function impossibleExampleNumberProvider(): iterable
    {
        foreach (PhoneRegionExampleNumbersTest::exampleNumberProvider() as $name => $case) {
            if (in_array(needle: $name, haystack: PhoneRegionExampleNumbersTest::NOT_POSSIBLE, strict: true)) {
                yield $name => $case;
            }
        }
    }

    #[DataProvider('impossibleExampleNumberProvider')]
    public function testExampleNumberThatIsNotPossibleForItsRegionThrows(
        string $region,
        string $type,
        string $example,
    ): void {
        $this->expectException(PhoneParseException::class);
        $this->expectExceptionMessageIs('The supplied phone number is not possible.');

        PhoneNumber::createFromString(input: $example, defaultCountryCode: $region);
    }
}
