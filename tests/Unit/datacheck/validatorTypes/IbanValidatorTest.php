<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\datacheck\validatorTypes;

use actra\yuf\datacheck\validatorTypes\IbanValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IbanValidatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function validIbanProvider(): iterable
    {
        yield 'CH with spaces' => ['CH93 0076 2011 6238 5295 7'];
        yield 'CH without spaces' => ['CH9300762011623852957'];
        yield 'CH lower case' => ['ch9300762011623852957'];
        yield 'LI with letters' => ['LI21 0881 0000 2324 013A A'];
        yield 'DE' => ['DE89 3704 0044 0532 0130 00'];
        yield 'AT' => ['AT61 1904 3002 3457 3201'];
        yield 'GB with letters' => ['GB82 WEST 1234 5698 7654 32'];
        yield 'FR with letters' => ['FR14 2004 1010 0505 0001 3M02 606'];
        yield 'IT with letters' => ['IT60 X054 2811 1010 0000 0123 456'];
        yield 'NL with letters' => ['NL91 ABNA 0417 1643 00'];
        yield 'ES' => ['ES91 2100 0418 4502 0005 1332'];
        yield 'BE shortest of the table' => ['BE68 5390 0754 7034'];
        yield 'NO' => ['NO93 8601 1117 947'];
        yield 'spaces anywhere' => [' C H9300 7620116238 52957 '];
    }

    #[DataProvider('validIbanProvider')]
    public function testValidIbanIsAccepted(string $iban): void
    {
        $this->assertTrue(IbanValidator::validate(input: $iban));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIbanProvider(): iterable
    {
        yield 'wrong checksum' => ['CH93 0076 2011 6238 5295 8'];
        yield 'changed digit' => ['CH93 0076 2011 6238 5296 7'];
        yield 'unknown country' => ['US64 SVBK US6S 3300 9588 79'];
        yield 'empty' => [''];
        yield 'only spaces' => ['   '];
        yield 'only the country code' => ['CH'];
        yield 'text' => ['not an iban'];
        yield 'dash' => ['CH93-0076-2011-6238-5295-7'];
        yield 'tab instead of space' => ["CH93\t0076 2011 6238 5295 7"];
        yield 'trailing line break' => ["CH9300762011623852957\n"];
        yield 'non-ASCII letter' => ['CH93 0076 2011 6238 5295 ä'];
        yield 'too long' => [str_repeat(string: 'CH93', times: 20)];
        yield 'huge input' => ['CH' . str_repeat(string: '9', times: 100000)];
    }

    #[DataProvider('invalidIbanProvider')]
    public function testInvalidIbanIsRejected(string $iban): void
    {
        $this->assertFalse(IbanValidator::validate(input: $iban));
    }

    /**
     * An example IBAN of the registry for each supported country.
     *
     * @return iterable<string, array{string}>
     */
    public static function countryExampleProvider(): iterable
    {
        yield 'AL' => ['AL35202111090000000001234567'];
        yield 'AD' => ['AD1400080001001234567890'];
        yield 'AT' => ['AT611904300234573201'];
        yield 'AZ' => ['AZ96AZEJ00000000001234567890'];
        yield 'BH' => ['BH02CITI00001077181611'];
        yield 'BE' => ['BE68539007547034'];
        yield 'BA' => ['BA393385804800211234'];
        yield 'BR' => ['BR1800360305000010009795493C1'];
        yield 'BG' => ['BG18RZBB91550123456789'];
        yield 'CR' => ['CR23015108410026012345'];
        yield 'HR' => ['HR1723600001101234565'];
        yield 'CY' => ['CY21002001950000357001234567'];
        yield 'CZ' => ['CZ6508000000192000145399'];
        yield 'DK' => ['DK9520000123456789'];
        yield 'DO' => ['DO22ACAU00000000000123456789'];
        yield 'EE' => ['EE471000001020145685'];
        yield 'FO' => ['FO9264600123456789'];
        yield 'FI' => ['FI1410093000123458'];
        yield 'FR' => ['FR1420041010050500013M02606'];
        yield 'GE' => ['GE29NB0000000101904917'];
        yield 'DE' => ['DE89370400440532013000'];
        yield 'GI' => ['GI04BARC000001234567890'];
        yield 'GR' => ['GR1601101250000000012300695'];
        yield 'GL' => ['GL8964710123456789'];
        yield 'GT' => ['GT20AGRO00000000001234567890'];
        yield 'HU' => ['HU93116000060000000012345676'];
        yield 'IS' => ['IS750001121234563108962099'];
        yield 'IE' => ['IE29AIBK93115212345678'];
        yield 'IL' => ['IL620108000000099999999'];
        yield 'IT' => ['IT60X0542811101000000123456'];
        yield 'JO' => ['JO94CBJO0010000000000131000302'];
        yield 'KZ' => ['KZ563190000012344567'];
        yield 'KW' => ['KW81CBKU0000000000001234560101'];
        yield 'LV' => ['LV97HABA0012345678910'];
        yield 'LB' => ['LB92000700000000123123456123'];
        yield 'LI' => ['LI21088100002324013AA'];
        yield 'LT' => ['LT601010012345678901'];
        yield 'LU' => ['LU120010001234567891'];
        yield 'MK' => ['MK07200002785123453'];
        yield 'MT' => ['MT31MALT01100000000000000000123'];
        yield 'MR' => ['MR1300020001010000123456753'];
        yield 'MU' => ['MU43BOMM0101123456789101000MUR'];
        yield 'MC' => ['MC5810096180790123456789085'];
        yield 'MD' => ['MD21EX000000000001234567'];
        yield 'ME' => ['ME25505000012345678951'];
        yield 'NL' => ['NL91ABNA0417164300'];
        yield 'NO' => ['NO9386011117947'];
        yield 'PK' => ['PK36SCBL0000001123456702'];
        yield 'PS' => ['PS92PALS000000000400123456702'];
        yield 'PL' => ['PL10105000997603123456789123'];
        yield 'PT' => ['PT50002700000001234567833'];
        yield 'QA' => ['QA54QNBA000000000000693123456'];
        yield 'RO' => ['RO66BACX0000001234567890'];
        yield 'SM' => ['SM76P0854009812123456789123'];
        yield 'SA' => ['SA4420000001234567891234'];
        yield 'RS' => ['RS35105008123123123173'];
        yield 'SK' => ['SK3112000000198742637541'];
        yield 'SI' => ['SI56192001234567892'];
        yield 'ES' => ['ES9121000418450200051332'];
        yield 'SE' => ['SE7280000810340009783242'];
        yield 'CH' => ['CH9300762011623852957'];
        yield 'TN' => ['TN5904018104004942712345'];
        yield 'TR' => ['TR320010009999901234567890'];
        yield 'AE' => ['AE460090000000123456789'];
        yield 'GB' => ['GB82WEST12345698765432'];
        yield 'VG' => ['VG96VPVG0000012345678901'];
    }

    #[DataProvider('countryExampleProvider')]
    public function testExampleOfEverySupportedCountryIsAccepted(string $iban): void
    {
        $this->assertTrue(IbanValidator::validate(input: $iban));
    }

    #[DataProvider('countryExampleProvider')]
    public function testChangedCheckDigitsOfEverySupportedCountryAreRejected(string $iban): void
    {
        $checkDigits = (int) substr(string: $iban, offset: 2, length: 2);
        $changedIban = substr(string: $iban, offset: 0, length: 2)
            . str_pad(string: (string) (($checkDigits % 97) + 1), length: 2, pad_string: '0', pad_type: STR_PAD_LEFT)
            . substr(string: $iban, offset: 4);

        $this->assertFalse(IbanValidator::validate(input: $changedIban));
    }

    /**
     * @return string The IBAN of the country with the BBAN and correct check digits
     */
    private static function withValidCheckDigits(string $countryCode, string $bban): string
    {
        $digits = '';
        foreach (str_split(string: $bban . $countryCode . '00') as $character) {
            $digits .= ctype_digit(text: $character) ? $character : (string) (ord(character: $character) - 55);
        }
        $checkDigits = 98 - (int) bcmod(num1: $digits, num2: '97');

        return $countryCode . str_pad(string: (string) $checkDigits, length: 2, pad_string: '0', pad_type: STR_PAD_LEFT)
            . $bban;
    }

    #[DataProvider('countryExampleProvider')]
    public function testIbanOneCharacterTooShortIsRejectedWithCorrectCheckDigits(string $iban): void
    {
        $tooShort = IbanValidatorTest::withValidCheckDigits(
            countryCode: substr(string: $iban, offset: 0, length: 2),
            bban: substr(string: $iban, offset: 4, length: strlen(string: $iban) - 5),
        );

        $this->assertFalse(IbanValidator::validate(input: $tooShort));
    }

    #[DataProvider('countryExampleProvider')]
    public function testIbanOneCharacterTooLongIsRejectedWithCorrectCheckDigits(string $iban): void
    {
        $tooLong = IbanValidatorTest::withValidCheckDigits(
            countryCode: substr(string: $iban, offset: 0, length: 2),
            bban: substr(string: $iban, offset: 4) . '0',
        );

        $this->assertFalse(IbanValidator::validate(input: $tooLong));
    }

    #[DataProvider('countryExampleProvider')]
    public function testRebuiltExampleWithCorrectCheckDigitsIsAccepted(string $iban): void
    {
        $rebuilt = IbanValidatorTest::withValidCheckDigits(
            countryCode: substr(string: $iban, offset: 0, length: 2),
            bban: substr(string: $iban, offset: 4),
        );

        $this->assertSame($iban, $rebuilt);
        $this->assertTrue(IbanValidator::validate(input: $rebuilt));
    }

    /**
     * Lengths of the IBAN registry (SWIFT, via Wikipedia "IBAN formats by country").
     *
     * @return iterable<string, array{string, int}>
     */
    public static function lengthProvider(): iterable
    {
        foreach (
            [
                'AL' => 28, 'AD' => 24, 'AT' => 20, 'AZ' => 28, 'BH' => 22, 'BE' => 16, 'BA' => 20, 'BR' => 29,
                'BG' => 22, 'CR' => 22, 'HR' => 21, 'CY' => 28, 'CZ' => 24, 'DK' => 18, 'DO' => 28, 'EE' => 20,
                'FO' => 18, 'FI' => 18, 'FR' => 27, 'GE' => 22, 'DE' => 22, 'GI' => 23, 'GR' => 27, 'GL' => 18,
                'GT' => 28, 'HU' => 28, 'IS' => 26, 'IE' => 22, 'IL' => 23, 'IT' => 27, 'JO' => 30, 'KZ' => 20,
                'KW' => 30, 'LV' => 21, 'LB' => 28, 'LI' => 21, 'LT' => 20, 'LU' => 20, 'MK' => 19, 'MT' => 31,
                'MR' => 27, 'MU' => 30, 'MC' => 27, 'MD' => 24, 'ME' => 22, 'NL' => 18, 'NO' => 15, 'PK' => 24,
                'PS' => 29, 'PL' => 28, 'PT' => 25, 'QA' => 29, 'RO' => 24, 'SM' => 27, 'SA' => 24, 'RS' => 22,
                'SK' => 24, 'SI' => 19, 'ES' => 24, 'SE' => 24, 'CH' => 21, 'TN' => 24, 'TR' => 26, 'AE' => 23,
                'GB' => 22, 'VG' => 24,
            ] as $countryCode => $length
        ) {
            yield $countryCode => [$countryCode, $length];
        }
    }

    #[DataProvider('lengthProvider')]
    public function testExampleHasTheLengthOfTheRegistry(string $countryCode, int $length): void
    {
        $examples = iterator_to_array(iterator: IbanValidatorTest::countryExampleProvider());

        $this->assertSame($length, strlen(string: $examples[$countryCode][0] ?? ''));
    }

    public function testLengthIsCheckedAfterRemovingSpaces(): void
    {
        $tooShort = IbanValidatorTest::withValidCheckDigits(countryCode: 'CH', bban: '0076201162385295');

        $this->assertFalse(IbanValidator::validate(input: chunk_split(string: $tooShort, length: 4, separator: ' ')));
    }
}
