<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\datacheck\validatorTypes;

/**
 * Validates an international bank account number (IBAN): known country, characters and the mod-97 checksum.
 * The length per country is not checked. Spaces are ignored, the case does not matter.
 * Further information about the validation rules:
 * https://de.wikipedia.org/wiki/Internationale_Bankkontonummer#Validierung
 */
final class IbanValidator
{
    /** The longest IBAN has 34 characters (the longest supported country has 31); stops needless calculation */
    private const int MAX_LENGTH = 34;

    /** @var list<string> Supported countries (lower case) */
    private const array COUNTRY_CODES = [
        'al', 'ad', 'at', 'az', 'bh', 'be', 'ba', 'br', 'bg', 'cr', 'hr', 'cy', 'cz', 'dk', 'do', 'ee', 'fo', 'fi',
        'fr', 'ge', 'de', 'gi', 'gr', 'gl', 'gt', 'hu', 'is', 'ie', 'il', 'it', 'jo', 'kz', 'kw', 'lv', 'lb', 'li',
        'lt', 'lu', 'mk', 'mt', 'mr', 'mu', 'mc', 'md', 'me', 'nl', 'no', 'pk', 'ps', 'pl', 'pt', 'qa', 'ro', 'sm',
        'sa', 'rs', 'sk', 'si', 'es', 'se', 'ch', 'tn', 'tr', 'ae', 'gb', 'vg',
    ];

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
        if (!in_array(needle: $countryCode, haystack: IbanValidator::COUNTRY_CODES, strict: true)) {
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
