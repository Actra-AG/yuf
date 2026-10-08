<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\phone;

/**
 * Reduces a text to the digits of a phone number: letters become the digits of a phone keypad when the text has at
 * least three letters (vanity numbers), other scripts' digits become ASCII digits, everything else is dropped.
 *
 * Adapted work based on https://github.com/giggsey/libphonenumber-for-php , which was published
 * with "Apache License Version 2.0, January 2004" ( http://www.apache.org/licenses/ )
 *
 * @internal
 */
final class PhoneNumberNormalizer
{
    public static function normalize(string $number): string
    {
        if (preg_match(pattern: '/^' . PhonePatterns::VALID_ALPHA_PHONE_PATTERN . '$/ui', subject: $number) === 1) {
            return PhoneNumberNormalizer::normalizeLettersAndDigits(number: $number);
        }

        return PhoneNumberNormalizer::normalizeDigits(number: $number);
    }

    public static function normalizeDigits(string $number): string
    {
        $normalizedDigits = '';
        foreach (mb_str_split(string: $number) as $character) {
            if (array_key_exists(key: $character, array: PhoneConstants::NUMERIC_CHARACTERS)) {
                $normalizedDigits .= PhoneConstants::NUMERIC_CHARACTERS[$character];
            } elseif (array_key_exists(key: $character, array: PhoneConstants::ASCII_DIGIT_MAPPINGS)) {
                $normalizedDigits .= $character;
            }
        }

        return $normalizedDigits;
    }

    private static function normalizeLettersAndDigits(string $number): string
    {
        $normalizedNumber = '';
        foreach (mb_str_split(string: $number) as $character) {
            $upperCaseCharacter = mb_strtoupper(string: $character);
            if (array_key_exists(key: $upperCaseCharacter, array: PhoneConstants::ALPHA_PHONE_MAPPINGS)) {
                $normalizedNumber .= PhoneConstants::ALPHA_PHONE_MAPPINGS[$upperCaseCharacter];
            }
        }

        return $normalizedNumber;
    }
}
