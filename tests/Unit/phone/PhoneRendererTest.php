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

final class PhoneRendererTest extends TestCase
{
    /**
     * Example numbers of the metadata of the regions. Italy is tested separately.
     *
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function regionExampleProvider(): iterable
    {
        // region, number type, internal format, international format
        yield 'CH fixed line' => ['CH', 'fixedLine', '+41.212345678', '+41 21 234 56 78'];
        yield 'CH mobile' => ['CH', 'mobile', '+41.781234567', '+41 78 123 45 67'];
        yield 'DE fixed line' => ['DE', 'fixedLine', '+49.30123456', '+49 30 123456'];
        yield 'DE mobile' => ['DE', 'mobile', '+49.15123456789', '+49 1512 3456789'];
        yield 'AT fixed line' => ['AT', 'fixedLine', '+43.1234567890', '+43 1 234567890'];
        yield 'AT mobile' => ['AT', 'mobile', '+43.664123456', '+43 664 123456'];
        yield 'FR fixed line' => ['FR', 'fixedLine', '+33.123456789', '+33 1 23 45 67 89'];
        yield 'FR mobile' => ['FR', 'mobile', '+33.612345678', '+33 6 12 34 56 78'];
        yield 'LI fixed line' => ['LI', 'fixedLine', '+423.2345678', '+423 234 56 78'];
        yield 'LI mobile' => ['LI', 'mobile', '+423.660234567', '+423 660 234 567'];
        yield 'US fixed line' => ['US', 'fixedLine', '+1.2015550123', '+1 201-555-0123'];
        yield 'CA fixed line' => ['CA', 'fixedLine', '+1.5062345678', '+1 506-234-5678'];
        yield 'GB fixed line' => ['GB', 'fixedLine', '+44.1212345678', '+44 121 234 5678'];
        yield 'GB mobile' => ['GB', 'mobile', '+44.7400123456', '+44 7400 123456'];
        yield 'ES fixed line' => ['ES', 'fixedLine', '+34.810123456', '+34 810 12 34 56'];
        yield 'ES mobile' => ['ES', 'mobile', '+34.612345678', '+34 612 34 56 78'];
        yield 'NL fixed line' => ['NL', 'fixedLine', '+31.101234567', '+31 10 123 4567'];
        yield 'NL mobile' => ['NL', 'mobile', '+31.612345678', '+31 6 12345678'];
        yield 'PL fixed line' => ['PL', 'fixedLine', '+48.123456789', '+48 12 345 67 89'];
        yield 'PL mobile' => ['PL', 'mobile', '+48.512345678', '+48 512 345 678'];
        yield 'JP fixed line' => ['JP', 'fixedLine', '+81.312345678', '+81 3-1234-5678'];
        yield 'JP mobile' => ['JP', 'mobile', '+81.9012345678', '+81 90-1234-5678'];
        yield 'AU fixed line' => ['AU', 'fixedLine', '+61.212345678', '+61 2 1234 5678'];
        yield 'AU mobile' => ['AU', 'mobile', '+61.412345678', '+61 412 345 678'];
        yield 'BR fixed line' => ['BR', 'fixedLine', '+55.1123456789', '+55 11 2345-6789'];
        yield 'BR mobile' => ['BR', 'mobile', '+55.11961234567', '+55 11 96123-4567'];
        yield 'IN fixed line' => ['IN', 'fixedLine', '+91.7410410123', '+91 74104 10123'];
        yield 'IN mobile' => ['IN', 'mobile', '+91.8123456789', '+91 81234 56789'];
        yield 'MX mobile (trunk prefix with 1)' => ['MX', 'mobile', '+52.2221234567', '+52 222 123 4567'];
        yield 'RU fixed line' => ['RU', 'fixedLine', '+7.3011234567', '+7 301 123-45-67'];
        yield 'RU mobile' => ['RU', 'mobile', '+7.9123456789', '+7 912 345-67-89'];
        yield 'CN fixed line' => ['CN', 'fixedLine', '+86.1012345678', '+86 10 1234 5678'];
        yield 'CN mobile' => ['CN', 'mobile', '+86.13123456789', '+86 131 2345 6789'];
        yield 'GA fixed line (no format)' => ['GA', 'fixedLine', '+241.1441234', '+241 1441234'];
        yield 'GA mobile' => ['GA', 'mobile', '+241.6031234', '+241 6 03 12 34'];
    }

    #[DataProvider('regionExampleProvider')]
    public function testRenderInternalFormat(
        string $region,
        string $type,
        string $internal,
        string $international,
    ): void {
        $phoneNumber = $this->parseExample(region: $region, type: $type);

        $this->assertSame($internal, PhoneRenderer::renderInternalFormat(phoneNumber: $phoneNumber));
    }

    #[DataProvider('regionExampleProvider')]
    public function testRenderInternationalFormat(
        string $region,
        string $type,
        string $internal,
        string $international,
    ): void {
        $phoneNumber = $this->parseExample(region: $region, type: $type);

        $this->assertSame($international, PhoneRenderer::renderInternationalFormat(phoneNumber: $phoneNumber));
    }

    private function parseExample(string $region, string $type): PhoneNumber
    {
        return PhoneNumber::createFromString(
            input: PhoneExampleNumbers::get(regionOrCallingCode: $region, type: $type),
            defaultCountryCode: $region,
        );
    }

    public function testItalianLandlineIsRenderedInternationalWithLeadingZero(): void
    {
        $phoneNumber = $this->parseExample(region: 'IT', type: 'fixedLine');

        $this->assertSame('+39 02 1234 5678', PhoneRenderer::renderInternationalFormat(phoneNumber: $phoneNumber));
    }

    public function testItalianLandlineKeepsItsLeadingZeroInTheInternalFormat(): void
    {
        $phoneNumber = $this->parseExample(region: 'IT', type: 'fixedLine');

        $this->assertSame('+39.0212345678', PhoneRenderer::renderInternalFormat(phoneNumber: $phoneNumber));
    }

    public function testInternalFormatOfAnItalianLandlineIsParsedToTheSameNumber(): void
    {
        $phoneNumber = $this->parseExample(region: 'IT', type: 'fixedLine');

        $parsedAgain = PhoneNumber::createFromString(
            input: PhoneRenderer::renderInternalFormat(phoneNumber: $phoneNumber),
            defaultCountryCode: 'CH',
        );

        $this->assertEquals($phoneNumber, $parsedAgain);
    }

    public function testItalianMobileNumberIsRenderedInternational(): void
    {
        $phoneNumber = $this->parseExample(region: 'IT', type: 'mobile');

        $this->assertSame('+39 312 345 6789', PhoneRenderer::renderInternationalFormat(phoneNumber: $phoneNumber));
        $this->assertSame('+39.3123456789', PhoneRenderer::renderInternalFormat(phoneNumber: $phoneNumber));
    }

    public function testVaticanLandlineIsRenderedLikeItalianOne(): void
    {
        $phoneNumber = $this->parseExample(region: 'VA', type: 'fixedLine');

        $this->assertSame('+39 06 6981 2345', PhoneRenderer::renderInternationalFormat(phoneNumber: $phoneNumber));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function nonGeographicalProvider(): iterable
    {
        yield 'international freephone' => ['+800 1234 5678', '+800.12345678', '+800 1234 5678'];
        yield 'international premium rate' => ['+979 1 2345 6789', '+979.123456789', '+979 1 2345 6789'];
        yield 'satellite network' => ['+882 34 21234', '+882.3421234', '+882 34 21234'];
    }

    #[DataProvider('nonGeographicalProvider')]
    public function testRenderNonGeographicalNumber(string $input, string $internal, string $international): void
    {
        $phoneNumber = PhoneNumber::createFromString(input: $input, defaultCountryCode: null);

        $this->assertSame($internal, PhoneRenderer::renderInternalFormat(phoneNumber: $phoneNumber));
        $this->assertSame($international, PhoneRenderer::renderInternationalFormat(phoneNumber: $phoneNumber));
    }

    public function testExtensionIsAppendedWithTheDefaultPrefix(): void
    {
        $phoneNumber = new PhoneNumber(
            extension: '12',
            countryCode: 41,
            italianLeadingZero: false,
            numberOfLeadingZeros: 1,
            nationalNumber: '446681800',
        );

        $this->assertSame(
            '+41 44 668 18 00 ext. 12',
            PhoneRenderer::renderInternationalFormat(phoneNumber: $phoneNumber),
        );
    }

    public function testExtensionIsNotPartOfTheInternalFormat(): void
    {
        $phoneNumber = new PhoneNumber(
            extension: '12',
            countryCode: 41,
            italianLeadingZero: false,
            numberOfLeadingZeros: 1,
            nationalNumber: '446681800',
        );

        $this->assertSame('+41.446681800', PhoneRenderer::renderInternalFormat(phoneNumber: $phoneNumber));
    }

    /**
     * @return iterable<string, array{int, string, string}>
     */
    public static function extensionPrefixProvider(): iterable
    {
        yield 'GB has its own prefix' => [44, '2079460958', '+44 20 7946 0958 x12'];
        yield 'NANP has the default prefix' => [1, '5062345678', '+1 506-234-5678 ext. 12'];
        yield 'JP has the default prefix' => [81, '312345678', '+81 3-1234-5678 ext. 12'];
    }

    #[DataProvider('extensionPrefixProvider')]
    public function testExtensionUsesThePreferredPrefixOfTheRegion(
        int $countryCode,
        string $nationalNumber,
        string $expected,
    ): void {
        $phoneNumber = new PhoneNumber(
            extension: '12',
            countryCode: $countryCode,
            italianLeadingZero: false,
            numberOfLeadingZeros: 1,
            nationalNumber: $nationalNumber,
        );

        $this->assertSame($expected, PhoneRenderer::renderInternationalFormat(phoneNumber: $phoneNumber));
    }

    public function testNumberWithUnknownCountryCodeIsRenderedAsNationalSignificantNumber(): void
    {
        $phoneNumber = new PhoneNumber(
            extension: '12',
            countryCode: 999,
            italianLeadingZero: false,
            numberOfLeadingZeros: 1,
            nationalNumber: '12345',
        );

        $this->assertSame('12345', PhoneRenderer::renderInternationalFormat(phoneNumber: $phoneNumber));
        $this->assertSame('+999.12345', PhoneRenderer::renderInternalFormat(phoneNumber: $phoneNumber));
    }

    public function testNumberThatMatchesNoFormatIsRenderedUnformatted(): void
    {
        $phoneNumber = new PhoneNumber(
            extension: '',
            countryCode: 41,
            italianLeadingZero: false,
            numberOfLeadingZeros: 1,
            nationalNumber: '4466818',
        );

        $this->assertSame('+41 4466818', PhoneRenderer::renderInternationalFormat(phoneNumber: $phoneNumber));
    }

    public function testNationalNumberWithTwoItalianLeadingZerosIsRenderedUnformatted(): void
    {
        $phoneNumber = new PhoneNumber(
            extension: '',
            countryCode: 39,
            italianLeadingZero: true,
            numberOfLeadingZeros: 2,
            nationalNumber: '12345678',
        );

        $this->assertSame('+39 0012345678', PhoneRenderer::renderInternationalFormat(phoneNumber: $phoneNumber));
    }
}
