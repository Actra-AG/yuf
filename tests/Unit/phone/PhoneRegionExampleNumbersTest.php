<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\phone;

use actra\yuf\phone\PhoneNumber;
use actra\yuf\phone\PhoneRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Parses and renders every example number of the metadata of every region.
 */
final class PhoneRegionExampleNumbersTest extends TestCase
{
    /**
     * Example numbers that start with the trunk prefix of the region: the national number is without it.
     */
    private const array WITH_TRUNK_PREFIX = ['MX mobile'];

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

    public function testTheMetadataHasExampleNumbersForEveryRegion(): void
    {
        $this->assertCount(245, PhoneExampleNumbers::regions());
        $this->assertSame(1096, iterator_count(PhoneRegionExampleNumbersTest::exampleNumberProvider()));
    }

    #[DataProvider('exampleNumberProvider')]
    public function testExampleNumberIsParsedWithTheCountryCodeOfItsRegion(
        string $region,
        string $type,
        string $example,
    ): void {
        $phoneNumber = PhoneNumber::createFromString(input: $example, defaultCountryCode: $region);

        $this->assertSame(PhoneExampleNumbers::countryCode(region: $region), $phoneNumber->countryCode);
        $this->assertSame('', $phoneNumber->extension);
    }

    #[DataProvider('exampleNumberProvider')]
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

    #[DataProvider('exampleNumberProvider')]
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

    #[DataProvider('exampleNumberProvider')]
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

    #[DataProvider('exampleNumberProvider')]
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

    #[DataProvider('exampleNumberProvider')]
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
}
