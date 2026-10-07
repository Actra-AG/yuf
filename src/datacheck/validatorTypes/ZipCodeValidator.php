<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\datacheck\validatorTypes;

/**
 * Checks a zip code against the format of a country. Countries without a format here accept every zip code.
 *
 * =============================================================
 * this class uses original source (10.2016) from:
 * http://www.pixelenvision.com/1708/zip-postal-code-validation-regex-php-code-for-12-countries/
 * License: "This code is free to use, distribute, modify and study. When referencing
 *           please link back to this website / post in any way e.g. direct link, credits etc."
 * -------------------------------------------------------------
 * RegEx for CH from https://jonaswitmer.ch/projekte/18-regex-fuer-schweizer-postleitzahlen-und-telefonnummern ,
 * licensed under https://creativecommons.org/licenses/by/3.0/ch/deed.de_CH , adapted to code
 * =============================================================
 */
final class ZipCodeValidator
{
    private const int MAX_LENGTH = 16;

    /** @var array<string, string> Regular expressions (without delimiters, matched case-insensitively) by country */
    private const array REGULAR_EXPRESSIONS = [
        'DE' => '\b((?:0[1-46-9]\d{3})|(?:[1-357-9]\d{4})|(?:[4][0-24-9]\d{3})|(?:[6][013-9]\d{3}))\b',
        'CH' => '^([1-468][0-9]|[57][0-7]|9[0-6])[0-9]{2}$',
        'AT' => '^[1-9][0-9]{3}$',
    ];

    /**
     * @param string $zipCode Surrounding whitespace is ignored
     * @param string $countryCode Upper case ISO code, e.g. `CH`; other values (and unknown countries) are not checked
     */
    public static function validate(string $zipCode, string $countryCode): bool
    {
        $zipCode = trim(string: $zipCode);
        if (strlen(string: $zipCode) > ZipCodeValidator::MAX_LENGTH) {
            return false;
        }
        if (!array_key_exists(key: $countryCode, array: ZipCodeValidator::REGULAR_EXPRESSIONS)) {
            return true;
        }

        return preg_match(
            pattern: '/' . ZipCodeValidator::REGULAR_EXPRESSIONS[$countryCode] . '/i',
            subject: $zipCode,
        ) === 1;
    }
}
