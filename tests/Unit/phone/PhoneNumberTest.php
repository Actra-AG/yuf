<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\phone;

use actra\yuf\phone\PhoneNumber;
use actra\yuf\phone\PhoneParseErrorEnum;
use actra\yuf\phone\PhoneParseException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string, int, string, string}>
     */
    public static function parsableNumberProvider(): iterable
    {
        // input, default region, country calling code, national number, extension
        yield 'international with plus' => ['+41 44 668 18 00', 'CH', 41, '446681800', ''];
        yield 'international with plus, no default region' => ['+41 44 668 18 00', null, 41, '446681800', ''];
        yield 'international with 00' => ['0041 44 668 18 00', 'CH', 41, '446681800', ''];
        yield 'international with 00, no default region' => ['00 41 44 668 18 00', null, 41, '446681800', ''];
        yield 'international with plus, other default region' => ['+41 44 668 18 00', 'DE', 41, '446681800', ''];
        yield 'international with 00, other default region' => ['0041446681800', 'DE', 41, '446681800', ''];
        yield 'international with the IDD of the default region' => ['011 41 44 668 18 00', 'US', 41, '446681800', ''];
        yield 'without separators' => ['+41446681800', 'CH', 41, '446681800', ''];
        yield 'national' => ['044 668 18 00', 'CH', 41, '446681800', ''];
        yield 'national with slash' => ['044/668 18 00', 'CH', 41, '446681800', ''];
        yield 'national with brackets and hyphens' => ['(044) 668-18-00', 'CH', 41, '446681800', ''];
        yield 'national without separators' => ['0446681800', 'CH', 41, '446681800', ''];
        yield 'national without the trunk prefix' => ['446681800', 'CH', 41, '446681800', ''];
        yield 'national with trunk prefix in brackets' => ['+41 (0)44 668 18 00', null, 41, '446681800', ''];
        yield 'German trunk prefix in brackets' => ['0049 (0)30 123456', 'CH', 49, '30123456', ''];
        yield 'surrounding whitespace' => ['  044 668 18 00  ', 'CH', 41, '446681800', ''];
        yield 'trailing dot' => ['044 668 18 00.', 'CH', 41, '446681800', ''];
        yield 'trailing hyphen' => ['044 668 18 00-', 'CH', 41, '446681800', ''];
        yield 'trailing semicolon' => ['044 668 18 00;', 'CH', 41, '446681800', ''];
        yield 'trailing exclamation mark' => ['044 668 18 00!', 'CH', 41, '446681800', ''];
        yield 'trailing comma' => ['044 668 18 00,', 'CH', 41, '446681800', ''];
        yield 'trailing quote' => ['+41 44 668 18 00"', 'CH', 41, '446681800', ''];
        yield 'quoted' => ['"044 668 18 00"', 'CH', 41, '446681800', ''];
        yield 'trailing symbols and spaces' => ['044 668 18 00 & ] !', 'CH', 41, '446681800', ''];
        yield 'trailing symbol after full-width digits' => [
            "\u{FF10}\u{FF14}\u{FF14} \u{FF16}\u{FF16}\u{FF18} \u{FF11}\u{FF18} \u{FF10}\u{FF10};",
            'CH',
            41,
            '446681800',
            '',
        ];
        yield 'trailing symbol after an extension' => ['044 668 18 00 ext. 123;', 'CH', 41, '446681800', '123'];
        yield 'enclosing brackets' => ['(044 668 18 00)', 'CH', 41, '446681800', ''];
        yield 'leading text' => ['Tel: 044 668 18 00', 'CH', 41, '446681800', ''];
        yield 'double plus' => ['++41 44 668 18 00', null, 41, '446681800', ''];
        yield 'full-width plus' => ["\u{FF0B}41 44 668 18 00", null, 41, '446681800', ''];
        yield 'full-width digits' => [
            "\u{FF10}\u{FF14}\u{FF14} \u{FF16}\u{FF16}\u{FF18} \u{FF11}\u{FF18} \u{FF10}\u{FF10}",
            'CH',
            41,
            '446681800',
            '',
        ];
        yield 'full-width digits after the plus' => [
            "+\u{FF14}\u{FF11} \u{FF14}\u{FF14} \u{FF16}\u{FF16}\u{FF18} \u{FF11}\u{FF18} \u{FF10}\u{FF10}",
            null,
            41,
            '446681800',
            '',
        ];
        yield 'Arabic-Indic digits' => [
            "\u{0660}\u{0664}\u{0664} \u{0666}\u{0666}\u{0668} \u{0661}\u{0668} \u{0660}\u{0660}",
            'CH',
            41,
            '446681800',
            '',
        ];
        yield 'vanity number' => ['0800 CALLME', 'CH', 41, '800225563', ''];
        yield 'extension with ext.' => ['044 668 18 00 ext. 123', 'CH', 41, '446681800', '123'];
        yield 'extension with x' => ['044 668 18 00 x123', 'CH', 41, '446681800', '123'];
        yield 'extension with hash' => ['044 668 18 00#123', 'CH', 41, '446681800', '123'];
        yield 'extension of seven digits' => ['+41 44 668 18 00 ext. 1234567', 'CH', 41, '446681800', '1234567'];
        yield 'extension longer than seven digits is cut' => [
            '+41 44 668 18 00 ext. 12345678',
            'CH',
            41,
            '446681800',
            '1234567',
        ];
        yield 'extension with ext and spaces' => ['+1 (506) 234-5678 ext 5', 'US', 1, '5062345678', '5'];
        yield 'RFC 3966' => ['tel:+41-44-668-18-00', 'CH', 41, '446681800', ''];
        yield 'RFC 3966 with phone context' => ['tel:044-668-18-00;phone-context=+41', null, 41, '446681800', ''];
        yield 'RFC 3966 with domain as phone context' => [
            'tel:044-668-18-00;phone-context=example.com',
            'CH',
            41,
            '446681800',
            '',
        ];
        yield 'RFC 3966 with ISDN subaddress' => ['tel:+41-44-668-18-00;isub=123', 'CH', 41, '446681800', ''];
        yield 'RFC 3966 with extension' => ['tel:+41-44-668-18-00;ext=77', 'CH', 41, '446681800', '77'];
        yield 'German' => ['030 123456', 'DE', 49, '30123456', ''];
        yield 'German with 00' => ['0049 30 123456', 'CH', 49, '30123456', ''];
        yield 'British with 00' => ['0044 20 7946 0958', 'CH', 44, '2079460958', ''];
        yield 'British with plus' => ['+44 20 7946 0958', null, 44, '2079460958', ''];
        yield 'NANP with trunk prefix' => ['1 506 234 5678', 'CA', 1, '5062345678', ''];
        yield 'NANP without trunk prefix' => ['506-234-5678', 'CA', 1, '5062345678', ''];
        yield 'NANP from Switzerland' => ['+1 506 234 5678', 'CH', 1, '5062345678', ''];
        yield 'international freephone' => ['+800 1234 5678', null, 800, '12345678', ''];
        yield 'satellite network' => ['+882 34 21234', 'CH', 882, '3421234', ''];
        yield 'international premium rate' => ['+979 1 2345 6789', 'CH', 979, '123456789', ''];
    }

    #[DataProvider('parsableNumberProvider')]
    public function testCreateFromStringParsesTheNumber(
        string $input,
        ?string $defaultCountryCode,
        int $expectedCountryCode,
        string $expectedNationalNumber,
        string $expectedExtension,
    ): void {
        $phoneNumber = PhoneNumber::createFromString(input: $input, defaultCountryCode: $defaultCountryCode);

        $this->assertSame($expectedCountryCode, $phoneNumber->countryCode);
        $this->assertSame($expectedNationalNumber, $phoneNumber->nationalNumber);
        $this->assertSame($expectedExtension, $phoneNumber->extension);
    }

    public function testNumberWithoutLeadingZerosHasOneAndNoItalianLeadingZero(): void
    {
        $phoneNumber = PhoneNumber::createFromString(input: '+41 44 668 18 00', defaultCountryCode: null);

        $this->assertFalse($phoneNumber->italianLeadingZero);
        $this->assertSame(1, $phoneNumber->numberOfLeadingZeros);
    }

    public function testTrailingTextWithLettersIsKept(): void
    {
        $phoneNumber = PhoneNumber::createFromString(input: '+41 44 668 18 00 abc', defaultCountryCode: null);

        $this->assertSame('446681800222', $phoneNumber->nationalNumber);
    }

    public function testItalianLandlineKeepsItsLeadingZero(): void
    {
        $phoneNumber = PhoneNumber::createFromString(input: '02 1234 5678', defaultCountryCode: 'IT');

        $this->assertSame(39, $phoneNumber->countryCode);
        $this->assertSame('212345678', $phoneNumber->nationalNumber);
        $this->assertTrue($phoneNumber->italianLeadingZero);
        $this->assertSame(1, $phoneNumber->numberOfLeadingZeros);
        $this->assertSame('0212345678', $phoneNumber->getNationalSignificantNumber());
    }

    public function testVaticanLandlineKeepsItsLeadingZero(): void
    {
        $phoneNumber = PhoneNumber::createFromString(input: '06 6981 2345', defaultCountryCode: 'VA');

        $this->assertSame(39, $phoneNumber->countryCode);
        $this->assertTrue($phoneNumber->italianLeadingZero);
        $this->assertSame('0669812345', $phoneNumber->getNationalSignificantNumber());
    }

    public function testItalianLandlineFromSwitzerlandKeepsItsLeadingZero(): void
    {
        $phoneNumber = PhoneNumber::createFromString(input: '0039 06 6981 2345', defaultCountryCode: 'CH');

        $this->assertTrue($phoneNumber->italianLeadingZero);
        $this->assertSame('669812345', $phoneNumber->nationalNumber);
    }

    public function testItalianMobileNumberHasUnsetItalianLeadingZero(): void
    {
        $phoneNumber = PhoneNumber::createFromString(input: '+39 3123456789', defaultCountryCode: 'CH');

        $this->assertNull($phoneNumber->italianLeadingZero);
        $this->assertSame('3123456789', $phoneNumber->getNationalSignificantNumber());
    }

    /**
     * @return iterable<string, array{string, ?string, int}>
     */
    public static function invalidNumberProvider(): iterable
    {
        // input, default region, error code of the exception
        yield 'empty' => ['', 'CH', 0];
        yield 'whitespace only' => ['   ', 'CH', 0];
        yield 'empty without default region' => ['', null, 0];
        yield 'letters' => ['abc', 'CH', 2];
        yield 'plus only' => ['+', 'CH', 2];
        yield 'double zero only' => ['00', 'CH', 2];
        yield 'single zero' => ['0', 'CH', 2];
        yield 'single digit' => ['1', 'CH', 2];
        yield 'country calling code only' => ['+41', 'CH', 2];
        yield 'one digit after the plus' => ['+4', 'CH', 2];
        yield 'international prefix and country calling code only' => ['0041', 'CH', 2];
        yield 'RFC 3966 prefix only' => ['tel:', 'CH', 2];
        yield 'empty extension' => ['+41 44 668 18 00;ext=', 'CH', 2];
        yield 'text before the digits' => ['0xyz', 'CH', 2];
        yield 'national number without default region' => ['044 668 18 00', null, 1];
        yield 'national number with lower case region' => ['044 668 18 00', 'ch', 1];
        yield 'national number with unknown region ZZ' => ['044 668 18 00', 'ZZ', 1];
        yield 'national number with region 001' => ['044 668 18 00', '001', 1];
        yield 'national number with unknown region' => ['044 668 18 00', 'XX', 1];
        yield 'unknown country calling code' => ['+999 1234567', 'CH', 1];
        yield 'country calling code starting with zero' => ['+0 12345 678', 'CH', 1];
        yield 'IDD without enough digits after it' => ['011 41', 'US', 3];
        yield 'country calling code without national number' => ['+411', 'CH', 4];
        yield 'one digit national number' => ['+41 1', 'CH', 4];
        yield 'national number of one digit after the IDD' => ['0041 4', 'CH', 4];
        yield 'too long for a national number' => ['0446681800000000000000', 'CH', 5];
        yield 'two numbers' => ['044 668 18 00 44 668 18 00 44', 'CH', 5];
        yield 'too long with international prefix' => ['+41 44 668 18 00 123456789', 'CH', 5];
        yield 'text with many digits at the end' => ['044 668 18 00 Durchwahl 12', 'CH', 5];
        yield 'more than 250 characters' => ['044' . '1111111111' . str_repeat(string: '1', times: 250), 'CH', 5];
        yield 'two digits are not possible' => ['12', 'CH', -1];
        yield 'three digits are not possible' => ['123', 'CH', -1];
        yield 'too short for Switzerland' => ['0446681', 'CH', -1];
        yield 'one digit too short for Switzerland' => ['04466818', 'CH', -1];
        yield 'one digit too long for Switzerland' => ['+41 4466818001', 'CH', -1];
        yield 'long digits for Switzerland' => ['+4144668180012345678', 'CH', -1];
        yield 'country calling code and two digits' => ['004144', 'CH', -1];
        yield 'short number with extension separator' => ['044 668 18 00 / 12', 'CH', -1];
        yield 'NANP number too short' => ['+1 23', 'CH', -1];
        yield 'only zeros' => ['0 0 0 0 0 0 0 0 0', 'CH', -1];
        yield 'double zero prefix and zeros' => ['00000000', 'IT', 1];
        yield 'letters make the number too long' => ['1-800-FLOWERS', 'CH', -1];
        yield 'IDD of another country' => ['011 44 20 7946 0958', 'CH', -1];
    }

    #[DataProvider('invalidNumberProvider')]
    public function testCreateFromStringThrowsForAnInvalidNumber(
        string $input,
        ?string $defaultCountryCode,
        int $expectedCode,
    ): void {
        try {
            PhoneNumber::createFromString(input: $input, defaultCountryCode: $defaultCountryCode);
            PhoneNumberTest::fail('Expected a PhoneParseException');
        } catch (PhoneParseException $phoneParseException) {
            $this->assertSame($expectedCode, $phoneParseException->getCode());
        }
    }

    /**
     * @return iterable<string, array{string, ?string, string}>
     */
    public static function exceptionMessageProvider(): iterable
    {
        yield 'empty' => ['', 'CH', 'The string is empty.'];
        yield 'not a number' => ['abc', 'CH', 'The string supplied did not seem to be a phone number.'];
        yield 'no region' => ['044 668 18 00', null, 'Missing or invalid default region.'];
        yield 'unknown country calling code' => ['+999 1234567', null, 'Could not interpret numbers after plus-sign.'];
        yield 'not possible' => ['12', 'CH', 'The supplied phone number is not possible.'];
        yield 'too long' => ['0446681800000000000000', 'CH', 'The string supplied is too long to be a phone number.'];
        yield 'too short' => ['+41 1', 'CH', 'The string supplied is too short to be a phone number.'];
        yield 'too short after IDD' => [
            '011 41',
            'US',
            'Phone number had an IDD, but after this was not long enough to be a viable phone number.',
        ];
    }

    #[DataProvider('exceptionMessageProvider')]
    public function testExceptionMessageSaysWhatIsWrong(
        string $input,
        ?string $defaultCountryCode,
        string $message,
    ): void {
        $this->expectException(PhoneParseException::class);
        $this->expectExceptionMessageIs($message);

        PhoneNumber::createFromString(input: $input, defaultCountryCode: $defaultCountryCode);
    }

    public function testPhoneParseExceptionHasTheErrorAsEnumAndAsCode(): void
    {
        $phoneParseException = new PhoneParseException(message: 'Test', error: PhoneParseErrorEnum::NOT_A_NUMBER);

        $this->assertSame(PhoneParseErrorEnum::NOT_A_NUMBER, $phoneParseException->error);
        $this->assertSame(2, $phoneParseException->getCode());
        $this->assertSame('Test', $phoneParseException->getMessage());
    }

    /**
     * @return iterable<string, array{int, PhoneParseErrorEnum}>
     */
    public static function errorCodeProvider(): iterable
    {
        yield 'empty string' => [0, PhoneParseErrorEnum::EMPTY_STRING];
        yield 'invalid country code' => [1, PhoneParseErrorEnum::INVALID_COUNTRY_CODE];
        yield 'not a number' => [2, PhoneParseErrorEnum::NOT_A_NUMBER];
        yield 'too short after IDD' => [3, PhoneParseErrorEnum::TOO_SHORT_AFTER_IDD];
        yield 'too short NSN' => [4, PhoneParseErrorEnum::TOO_SHORT_NSN];
        yield 'too long' => [5, PhoneParseErrorEnum::TOO_LONG];
        yield 'not possible' => [-1, PhoneParseErrorEnum::NOT_POSSIBLE];
    }

    #[DataProvider('errorCodeProvider')]
    public function testErrorEnumKeepsTheCodesOfTheFormerExceptionConstants(
        int $code,
        PhoneParseErrorEnum $expected,
    ): void {
        $this->assertSame($expected, PhoneParseErrorEnum::from($code));
    }

    public function testExceptionOfCreateFromStringHasTheErrorAsEnum(): void
    {
        try {
            PhoneNumber::createFromString(input: '12', defaultCountryCode: 'CH');
            PhoneNumberTest::fail('Expected a PhoneParseException');
        } catch (PhoneParseException $phoneParseException) {
            $this->assertSame(PhoneParseErrorEnum::NOT_POSSIBLE, $phoneParseException->error);
        }
    }

    public function testGetNationalSignificantNumberWithoutLeadingZero(): void
    {
        $phoneNumber = new PhoneNumber(
            extension: '',
            countryCode: 41,
            italianLeadingZero: false,
            numberOfLeadingZeros: 1,
            nationalNumber: '446681800',
        );

        $this->assertSame('446681800', $phoneNumber->getNationalSignificantNumber());
    }

    public function testGetNationalSignificantNumberWithSeveralLeadingZeros(): void
    {
        $phoneNumber = new PhoneNumber(
            extension: '',
            countryCode: 39,
            italianLeadingZero: true,
            numberOfLeadingZeros: 2,
            nationalNumber: '12345678',
        );

        $this->assertSame('0012345678', $phoneNumber->getNationalSignificantNumber());
    }

    public function testGetNationalSignificantNumberIgnoresLeadingZerosWithoutItalianLeadingZero(): void
    {
        $phoneNumber = new PhoneNumber(
            extension: '',
            countryCode: 39,
            italianLeadingZero: null,
            numberOfLeadingZeros: 3,
            nationalNumber: '12345678',
        );

        $this->assertSame('12345678', $phoneNumber->getNationalSignificantNumber());
    }

    public function testGetNationalSignificantNumberIgnoresZeroCount(): void
    {
        $phoneNumber = new PhoneNumber(
            extension: '',
            countryCode: 39,
            italianLeadingZero: true,
            numberOfLeadingZeros: 0,
            nationalNumber: '12345678',
        );

        $this->assertSame('12345678', $phoneNumber->getNationalSignificantNumber());
    }
}
