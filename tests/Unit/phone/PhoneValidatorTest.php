<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\phone;

use actra\yuf\phone\PhoneLengthResultEnum;
use actra\yuf\phone\PhoneMetaDataRepository;
use actra\yuf\phone\PhoneNumber;
use actra\yuf\phone\PhoneValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneValidatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function viableNumberProvider(): iterable
    {
        yield 'two digits' => ['12', true];
        yield 'one digit' => ['1', false];
        yield 'empty' => ['', false];
        yield 'only letters' => ['abc', false];
        yield 'only plus' => ['+', false];
        yield 'digits and a letter' => ['12a', false];
        yield 'with plus' => ['+41 44', true];
        yield 'national' => ['044 668 18 00', true];
        yield 'Arabic-Indic digits' => [
            "\u{0660}\u{0664}\u{0664}\u{0666}\u{0666}\u{0668}\u{0661}\u{0668}\u{0660}\u{0660}",
            true,
        ];
        yield 'with extension' => ['044 668 18 00 ext. 12', true];
        yield 'with letters (vanity number)' => ['1234 abc', true];
    }

    #[DataProvider('viableNumberProvider')]
    public function testIsViablePhoneNumber(string $number, bool $expected): void
    {
        $this->assertSame($expected, PhoneValidator::isViablePhoneNumber(number: $number));
    }

    /**
     * @return iterable<string, array{?string, bool}>
     */
    public static function regionCodeProvider(): iterable
    {
        yield 'Switzerland' => ['CH', true];
        yield 'United States' => ['US', true];
        yield 'Kosovo' => ['XK', true];
        yield 'lower case' => ['ch', false];
        yield 'unknown region ZZ' => ['ZZ', false];
        yield 'unknown region XX' => ['XX', false];
        yield 'non-geographical entity' => ['001', false];
        yield 'empty' => ['', false];
        yield 'null' => [null, false];
    }

    #[DataProvider('regionCodeProvider')]
    public function testIsValidRegionCode(?string $regionCode, bool $expected): void
    {
        $this->assertSame($expected, PhoneValidator::isValidRegionCode(regionCode: $regionCode));
    }

    /**
     * @return iterable<string, array{string, string, PhoneLengthResultEnum}>
     */
    public static function numberLengthProvider(): iterable
    {
        // region, number, result
        yield 'CH empty' => ['CH', '', PhoneLengthResultEnum::TOO_SHORT];
        yield 'CH one digit' => ['CH', '1', PhoneLengthResultEnum::TOO_SHORT];
        yield 'CH eight digits' => ['CH', '12345678', PhoneLengthResultEnum::TOO_SHORT];
        yield 'CH nine digits' => ['CH', '123456789', PhoneLengthResultEnum::IS_POSSIBLE];
        yield 'CH ten digits' => ['CH', '1234567890', PhoneLengthResultEnum::INVALID_LENGTH];
        yield 'CH eleven digits' => ['CH', '12345678901', PhoneLengthResultEnum::INVALID_LENGTH];
        yield 'CH twelve digits' => ['CH', '123456789012', PhoneLengthResultEnum::IS_POSSIBLE];
        yield 'CH thirteen digits' => ['CH', '1234567890123', PhoneLengthResultEnum::TOO_LONG];
        yield 'US seven digits (local only)' => ['US', '1234567', PhoneLengthResultEnum::IS_POSSIBLE_LOCAL_ONLY];
        yield 'US eight digits' => ['US', '12345678', PhoneLengthResultEnum::TOO_SHORT];
        yield 'US ten digits' => ['US', '1234567890', PhoneLengthResultEnum::IS_POSSIBLE];
        yield 'US eleven digits' => ['US', '12345678901', PhoneLengthResultEnum::TOO_LONG];
        yield 'GB local only length' => ['GB', '123456', PhoneLengthResultEnum::IS_POSSIBLE_LOCAL_ONLY];
        yield 'GB seven digits' => ['GB', '1234567', PhoneLengthResultEnum::IS_POSSIBLE];
        yield 'GB eight digits (local only)' => ['GB', '12345678', PhoneLengthResultEnum::IS_POSSIBLE_LOCAL_ONLY];
        yield 'GB nine digits' => ['GB', '123456789', PhoneLengthResultEnum::IS_POSSIBLE];
        yield 'GB eleven digits' => ['GB', '12345678901', PhoneLengthResultEnum::TOO_LONG];
    }

    #[DataProvider('numberLengthProvider')]
    public function testTestNumberLength(string $region, string $number, PhoneLengthResultEnum $expected): void
    {
        $phoneMetaData = new PhoneMetaDataRepository()->getForRegion(regionCode: $region);
        $this->assertNotNull($phoneMetaData);

        $this->assertSame($expected, PhoneValidator::testNumberLength(number: $number, phoneMetaData: $phoneMetaData));
    }

    /**
     * @return iterable<string, array{int, PhoneLengthResultEnum}>
     */
    public static function lengthResultCodeProvider(): iterable
    {
        yield 'possible' => [0, PhoneLengthResultEnum::IS_POSSIBLE];
        yield 'too short' => [2, PhoneLengthResultEnum::TOO_SHORT];
        yield 'too long' => [3, PhoneLengthResultEnum::TOO_LONG];
        yield 'possible, local only' => [4, PhoneLengthResultEnum::IS_POSSIBLE_LOCAL_ONLY];
        yield 'invalid length' => [5, PhoneLengthResultEnum::INVALID_LENGTH];
    }

    #[DataProvider('lengthResultCodeProvider')]
    public function testLengthResultsKeepTheValuesOfTheFormerConstants(
        int $value,
        PhoneLengthResultEnum $expected,
    ): void {
        $this->assertSame($expected, PhoneLengthResultEnum::from($value));
    }

    public function testIsPossibleNumber(): void
    {
        $phoneNumber = $this->createSwissNumber(nationalNumber: '446681800');

        $this->assertTrue($this->createValidator()->isPossibleNumber(phoneNumber: $phoneNumber));
    }

    public function testIsPossibleNumberIsFalseForALengthThatDoesNotFit(): void
    {
        $validator = $this->createValidator();

        $this->assertFalse(
            $validator->isPossibleNumber(phoneNumber: $this->createSwissNumber(nationalNumber: '4466818')),
        );
        $this->assertFalse(
            $validator->isPossibleNumber(phoneNumber: $this->createSwissNumber(nationalNumber: '1234567890')),
        );
    }

    public function testIsPossibleNumberAcceptsALocalOnlyLength(): void
    {
        $phoneNumber = new PhoneNumber(
            extension: '',
            countryCode: 1,
            italianLeadingZero: false,
            numberOfLeadingZeros: 1,
            nationalNumber: '5062345',
        );

        $this->assertTrue($this->createValidator()->isPossibleNumber(phoneNumber: $phoneNumber));
    }

    public function testIsPossibleNumberCountsTheItalianLeadingZero(): void
    {
        $phoneNumber = new PhoneNumber(
            extension: '',
            countryCode: 39,
            italianLeadingZero: true,
            numberOfLeadingZeros: 1,
            nationalNumber: '212345678',
        );

        $this->assertTrue($this->createValidator()->isPossibleNumber(phoneNumber: $phoneNumber));
    }

    public function testIsPossibleNumberIsFalseForAnUnknownCountryCode(): void
    {
        $phoneNumber = new PhoneNumber(
            extension: '',
            countryCode: 999,
            italianLeadingZero: false,
            numberOfLeadingZeros: 1,
            nationalNumber: '12345',
        );

        $this->assertFalse($this->createValidator()->isPossibleNumber(phoneNumber: $phoneNumber));
    }

    private function createValidator(): PhoneValidator
    {
        return new PhoneValidator(metaDataRepository: new PhoneMetaDataRepository());
    }

    private function createSwissNumber(string $nationalNumber): PhoneNumber
    {
        return new PhoneNumber(
            extension: '',
            countryCode: 41,
            italianLeadingZero: false,
            numberOfLeadingZeros: 1,
            nationalNumber: $nationalNumber,
        );
    }
}
