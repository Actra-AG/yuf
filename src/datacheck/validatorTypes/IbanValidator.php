<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\datacheck\validatorTypes;

/**
 * Validates an international bank account number (IBAN): known country, characters, the length of the country and
 * the mod-97 checksum. Spaces are ignored, the case does not matter.
 * Further information about the validation rules:
 * https://de.wikipedia.org/wiki/Internationale_Bankkontonummer#Validierung
 */
final readonly class IbanValidator
{
    /**
     * Supported countries (lower case) with the length of their IBAN. Source: IBAN registry of SWIFT (release 99,
     * December 2024) as listed in https://en.wikipedia.org/wiki/International_Bank_Account_Number, section "IBAN
     * formats by country".
     *
     * @var array<string, int>
     */
    private const array LENGTH_BY_COUNTRY_CODE = [
        'al' => 28, 'ad' => 24, 'at' => 20, 'az' => 28, 'bh' => 22, 'be' => 16, 'ba' => 20, 'br' => 29, 'bg' => 22,
        'cr' => 22, 'hr' => 21, 'cy' => 28, 'cz' => 24, 'dk' => 18, 'do' => 28, 'ee' => 20, 'fo' => 18, 'fi' => 18,
        'fr' => 27, 'ge' => 22, 'de' => 22, 'gi' => 23, 'gr' => 27, 'gl' => 18, 'gt' => 28, 'hu' => 28, 'is' => 26,
        'ie' => 22, 'il' => 23, 'it' => 27, 'jo' => 30, 'kz' => 20, 'kw' => 30, 'lv' => 21, 'lb' => 28, 'li' => 21,
        'lt' => 20, 'lu' => 20, 'mk' => 19, 'mt' => 31, 'mr' => 27, 'mu' => 30, 'mc' => 27, 'md' => 24, 'me' => 22,
        'nl' => 18, 'no' => 15, 'pk' => 24, 'ps' => 29, 'pl' => 28, 'pt' => 25, 'qa' => 29, 'ro' => 24, 'sm' => 27,
        'sa' => 24, 'rs' => 22, 'sk' => 24, 'si' => 19, 'es' => 24, 'se' => 24, 'ch' => 21, 'tn' => 24, 'tr' => 26,
        'ae' => 23, 'gb' => 22, 'vg' => 24,
    ];

    /** The longest IBAN has 34 characters (the longest supported country has 31); stops needless calculation */
    private const int MAX_LENGTH = 34;

    public static function validate(string $input): bool
    {
        $iban = strtolower(string: str_replace(search: ' ', replace: '', subject: $input));
        if (
            strlen(string: $iban) > IbanValidator::MAX_LENGTH
            || preg_match(pattern: '/\A[a-z0-9]+\z/', subject: $iban) !== 1
        ) {
            return false;
        }
        $countryCode = substr(string: $iban, offset: 0, length: 2);
        $expectedLength = IbanValidator::LENGTH_BY_COUNTRY_CODE[$countryCode] ?? null;
        if ($expectedLength === null || strlen(string: $iban) !== $expectedLength) {
            return false;
        }
        $movedIban = substr(string: $iban, offset: 4) . substr(string: $iban, offset: 0, length: 4);
        $digits = '';
        foreach (str_split(string: $movedIban) as $character) {
            // Letters count as numbers: a = 10, b = 11, ... z = 35
            $digits .= ctype_digit(text: $character) ? $character : (string) (ord(character: $character) - 87);
        }

        return (int) bcmod(num1: $digits, num2: '97') === 1;
    }
}
