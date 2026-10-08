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
 * The E.164, the international and the national format (as libphonenumber renders them).
 */
final class PhoneRendererFormatTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string, string, string, string}>
     */
    public static function formatProvider(): iterable
    {
        // input, default region, E.164, international, national
        yield 'CH fixed line' => ['044 123 45 67', 'CH', '+41441234567', '+41 44 123 45 67', '044 123 45 67'];
        yield 'CH mobile' => ['+41 79 123 45 67', null, '+41791234567', '+41 79 123 45 67', '079 123 45 67'];
        yield 'CH with extension' => [
            '+41 44 123 45 67 ext. 9',
            null,
            '+41441234567',
            '+41 44 123 45 67 ext. 9',
            '044 123 45 67 ext. 9',
        ];
        yield 'DE with national prefix' => ['030 123456', 'DE', '+4930123456', '+49 30 123456', '030 123456'];
        yield 'DE with extension' => [
            '+49 30 123456 ext. 12',
            null,
            '+4930123456',
            '+49 30 123456 ext. 12',
            '030 123456 ext. 12',
        ];
        yield 'DE mobile' => ['0151 23456789', 'DE', '+4915123456789', '+49 1512 3456789', '01512 3456789'];
        yield 'US' => ['650 253 0000', 'US', '+16502530000', '+1 650-253-0000', '(650) 253-0000'];
        yield 'US with extension' => [
            '+1 650 253 0000 x123',
            null,
            '+16502530000',
            '+1 650-253-0000 ext. 123',
            '(650) 253-0000 ext. 123',
        ];
        yield 'GB London' => ['020 7946 0018', 'GB', '+442079460018', '+44 20 7946 0018', '020 7946 0018'];
        yield 'GB mobile' => ['07912 345678', 'GB', '+447912345678', '+44 7912 345678', '07912 345678'];
        yield 'FR' => ['01 23 45 67 89', 'FR', '+33123456789', '+33 1 23 45 67 89', '01 23 45 67 89'];
        yield 'FR from international' => [
            '+33 6 12 34 56 78',
            null,
            '+33612345678',
            '+33 6 12 34 56 78',
            '06 12 34 56 78',
        ];
        yield 'IT fixed line keeps its zero' => [
            '06 1234 5678',
            'IT',
            '+390612345678',
            '+39 06 1234 5678',
            '06 1234 5678',
        ];
        yield 'IT mobile' => ['+39 312 345 6789', null, '+393123456789', '+39 312 345 6789', '312 345 6789'];
        yield 'RU national prefix 8' => [
            '+7 912 345-67-89',
            null,
            '+79123456789',
            '+7 912 345-67-89',
            '8 (912) 345-67-89',
        ];
        yield 'JP' => ['+81 90-1234-5678', null, '+819012345678', '+81 90-1234-5678', '090-1234-5678'];
        yield 'AR mobile (the rule stands for the first group)' => [
            '91123456789', 'AR', '+5491123456789', '+54 9 11 2345-6789', '011 15-2345-6789',
        ];
        yield 'BR' => ['+55 11 96123-4567', null, '+5511961234567', '+55 11 96123-4567', '(11) 96123-4567'];
    }

    #[DataProvider('formatProvider')]
    public function testE164Format(
        string $input,
        ?string $region,
        string $e164,
        string $international,
        string $national,
    ): void {
        $phoneNumber = PhoneNumber::createFromString(input: $input, defaultCountryCode: $region);

        $this->assertSame($e164, PhoneRenderer::renderE164Format(phoneNumber: $phoneNumber));
    }

    #[DataProvider('formatProvider')]
    public function testInternationalFormat(
        string $input,
        ?string $region,
        string $e164,
        string $international,
        string $national,
    ): void {
        $phoneNumber = PhoneNumber::createFromString(input: $input, defaultCountryCode: $region);

        $this->assertSame($international, PhoneRenderer::renderInternationalFormat(phoneNumber: $phoneNumber));
    }

    #[DataProvider('formatProvider')]
    public function testNationalFormat(
        string $input,
        ?string $region,
        string $e164,
        string $international,
        string $national,
    ): void {
        $phoneNumber = PhoneNumber::createFromString(input: $input, defaultCountryCode: $region);

        $this->assertSame($national, PhoneRenderer::renderNationalFormat(phoneNumber: $phoneNumber));
    }

    #[DataProvider('formatProvider')]
    public function testE164AndNationalFormatAreParsedToTheSameNumber(
        string $input,
        ?string $region,
        string $e164,
        string $international,
        string $national,
    ): void {
        $phoneNumber = PhoneNumber::createFromString(input: $input, defaultCountryCode: $region);

        $fromE164 = PhoneNumber::createFromString(input: $e164, defaultCountryCode: null);
        $fromNational = PhoneNumber::createFromString(
            input: PhoneRenderer::renderNationalFormat(phoneNumber: $phoneNumber),
            defaultCountryCode: $region ?? PhoneRendererFormatTest::regionOf(countryCode: $phoneNumber->countryCode),
        );

        $this->assertSame($phoneNumber->countryCode, $fromE164->countryCode);
        $this->assertSame($phoneNumber->getNationalSignificantNumber(), $fromE164->getNationalSignificantNumber());
        $this->assertSame($phoneNumber->countryCode, $fromNational->countryCode);
        $this->assertSame($phoneNumber->getNationalSignificantNumber(), $fromNational->getNationalSignificantNumber());
    }

    private static function regionOf(int $countryCode): string
    {
        return match ($countryCode) {
            41 => 'CH',
            49 => 'DE',
            1 => 'US',
            33 => 'FR',
            39 => 'IT',
            7 => 'RU',
            81 => 'JP',
            55 => 'BR',
            default => 'CH',
        };
    }

    public function testNumberWithUnknownCountryCallingCodeIsRenderedAsNationalSignificantNumber(): void
    {
        $phoneNumber = new PhoneNumber(
            extension: '12',
            countryCode: 999,
            italianLeadingZero: null,
            numberOfLeadingZeros: 0,
            nationalNumber: '12345',
        );

        $this->assertSame('+99912345', PhoneRenderer::renderE164Format(phoneNumber: $phoneNumber));
        $this->assertSame('12345', PhoneRenderer::renderNationalFormat(phoneNumber: $phoneNumber));
    }

    public function testNumberThatMatchesNoFormatIsRenderedNationalWithoutNationalPrefix(): void
    {
        $phoneNumber = new PhoneNumber(
            extension: '',
            countryCode: 41,
            italianLeadingZero: null,
            numberOfLeadingZeros: 0,
            nationalNumber: '4466818',
        );

        $this->assertSame('4466818', PhoneRenderer::renderNationalFormat(phoneNumber: $phoneNumber));
    }
}
