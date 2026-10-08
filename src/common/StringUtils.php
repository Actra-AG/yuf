<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

use InvalidArgumentException;

/**
 * Stateless string helpers (static on purpose: no state, no dependencies). Positions and lengths are counted in
 * characters (multibyte safe), unless the method says otherwise.
 */
final class StringUtils
{
    public const string IMPLODE_DEFAULT_SEPARATOR = ''; // https://github.com/php/php-src/issues/10197

    private const string RANDOM_LOWER_CASE_LETTERS = 'abcdefghjkmnpqrstuvwxyz';
    private const string RANDOM_UPPER_CASE_LETTERS = 'ABCDEFGHJKMNPQRSTUVWXYZ';
    private const string RANDOM_DIGITS = '23456789';
    private const string RANDOM_SPECIAL_CHARACTERS = '!@#$%&*?';
    private const string SALT_CHARACTERS = '`´°+*ç%&/()=?abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
        . '1234567890üöä!£{}éèà[]¢|¬§°#@¦';
    private const array BYTE_UNITS = ['B', 'KB', 'MB', 'GB', 'TB'];

    /**
     * @return string What follows the first `$after`, an empty string if it is not contained
     */
    public static function afterFirst(string $string, string $after): string
    {
        $position = mb_strpos(haystack: $string, needle: $after);
        if ($position === false) {
            return '';
        }

        return mb_substr(string: $string, start: $position + mb_strlen(string: $after));
    }

    /**
     * @return string What precedes the first `$before`, the whole string if it is not contained
     */
    public static function beforeFirst(string $string, string $before): string
    {
        $position = mb_strpos(haystack: $string, needle: $before);
        if ($position === false) {
            return $string;
        }

        return mb_substr(string: $string, start: 0, length: $position);
    }

    /**
     * @return ?string What lies between the first `$start` and the last `$end` after it, `null` if one of them is
     *                 not contained
     */
    public static function between(string $string, string $start, string $end): ?string
    {
        $startPosition = mb_strpos(haystack: $string, needle: $start);
        if ($startPosition === false) {
            return null;
        }
        $contentPosition = $startPosition + mb_strlen(string: $start);
        $endPosition = mb_strrpos(haystack: $string, needle: $end, offset: $contentPosition);
        if ($endPosition === false) {
            return null;
        }

        return mb_substr(string: $string, start: $contentPosition, length: $endPosition - $contentPosition);
    }

    /**
     * @return string The string with `$newString` inserted before the last `$beforeLast`; unchanged if `$beforeLast`
     *                is not contained
     */
    public static function insertBeforeLast(string $string, string $beforeLast, string $newString): string
    {
        $afterLast = StringUtils::afterLast(string: $string, after: $beforeLast);
        if ($afterLast === null) {
            return $string;
        }

        return StringUtils::beforeLast(string: $string, before: $beforeLast) . $newString . $beforeLast . $afterLast;
    }

    /**
     * @return string What precedes the last `$before`, the whole string if it is not contained
     */
    public static function beforeLast(string $string, string $before): string
    {
        $position = mb_strrpos(haystack: $string, needle: $before);
        if ($position === false) {
            return $string;
        }

        return mb_substr(string: $string, start: 0, length: $position);
    }

    /**
     * @return ?string What follows the last `$after`, `null` if it is not contained
     */
    public static function afterLast(string $string, string $after): ?string
    {
        $position = mb_strrpos(haystack: $string, needle: $after);
        if ($position === false) {
            return null;
        }

        return mb_substr(string: $string, start: $position + mb_strlen(string: $after));
    }

    /**
     * Shortens a sentence longer than `$atIndex` characters at the last space within the first `$atIndex` characters.
     */
    public static function breakUp(string $sentence, int $atIndex): string
    {
        if (mb_strlen(string: $sentence) > $atIndex) {
            return StringUtils::beforeLast(
                string: mb_substr(string: $sentence, start: 0, length: $atIndex),
                before: ' ',
            );
        }

        return $sentence;
    }

    /**
     * Splits at every byte of `$delimiters`; empty tokens are left out.
     *
     * @return list<string>
     */
    public static function tokenize(string $string, string $delimiters): array
    {
        $tokens = [];
        $length = strlen(string: $string);
        $position = 0;
        while ($position < $length) {
            $position += strspn(string: $string, characters: $delimiters, offset: $position);
            if ($position >= $length) {
                break;
            }
            $tokenLength = strcspn(string: $string, characters: $delimiters, offset: $position);
            $tokens[] = substr(string: $string, offset: $position, length: $tokenLength);
            $position += $tokenLength;
        }

        return $tokens;
    }

    /**
     * @param string|list<string> $separators One separator or several separators (empty ones are ignored in the list)
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException If the separator is an empty string
     */
    public static function explode(string|array $separators, string $string): array
    {
        if ($separators === '') {
            throw new InvalidArgumentException(message: 'The separator must not be an empty string.');
        }
        if (is_string(value: $separators)) {
            return explode(separator: $separators, string: $string);
        }
        $unitSeparator = chr(codepoint: 31);

        return explode(
            separator: $unitSeparator,
            string: str_replace(search: $separators, replace: $unitSeparator, subject: $string),
        );
    }

    /**
     * Makes a file name or URL part from a text: transliterated to ASCII (the result depends on the locale of the
     * process for characters like "ü"), lower case, everything except letters, digits, "." and "-" is replaced.
     *
     * @return string "unbenannt" if nothing is left
     */
    public static function urlify(string $string, string $separator = '-', int $maxLength = 0): string
    {
        $transliterated = iconv(from_encoding: 'UTF-8', to_encoding: 'ASCII//TRANSLIT', string: $string);
        $string = preg_replace(
            pattern: ['/[^a-zA-Z0-9\-.]/', '/-+/'],
            replacement: '-',
            subject: $transliterated === false ? '' : $transliterated,
        );
        $string = trim(string: $string ?? '', characters: '-');
        if ($separator !== '-') {
            $string = str_replace(search: '-', replace: $separator, subject: $string);
        }
        if ($string === '') {
            $string = 'unbenannt';
        }
        $string = strtolower(string: $string);

        return $maxLength === 0 ? $string : substr(string: $string, offset: 0, length: $maxLength);
    }

    public static function emptyToNull(string $string): ?string
    {
        return $string === '' ? null : $string;
    }

    /**
     * @throws InvalidArgumentException If the domain of the address cannot be converted
     */
    public static function utf8ToPunycodeEmail(string $email): string
    {
        return StringUtils::convertEmailDomain(
            email: $email,
            convert: static fn(string $domain): string|false => idn_to_ascii(domain: $domain),
        );
    }

    /**
     * @throws InvalidArgumentException If the domain of the address cannot be converted
     */
    public static function punycodeToUtf8Email(string $email): string
    {
        return StringUtils::convertEmailDomain(
            email: $email,
            convert: static fn(string $domain): string|false => idn_to_utf8(domain: $domain),
        );
    }

    /**
     * Formats a size with the unit B, KB, MB, GB or TB (1 KB = 1024 B); more than 1024 TB stays in TB.
     */
    public static function formatBytes(int|float $bytes, int $precision = 2): string
    {
        $value = max($bytes, 0);
        $unitIndex = 0;
        while ($value >= 1024 && $unitIndex < count(value: StringUtils::BYTE_UNITS) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return round(num: $value, precision: $precision) . ' ' . StringUtils::BYTE_UNITS[$unitIndex];
    }

    /**
     * Generates a random string with characters that are hard to mix up (no "0", "1", "i", "l", "o"). With at least
     * three characters (four with special characters), every group of characters (lower case, upper case, digits,
     * special characters) is contained. Cryptographically secure (random_int).
     *
     * @param int $requiredStringLength Length of the string, at least 1
     * @param bool $noSpecialChars Set to true to only use letters and digits
     */
    public static function randomString(int $requiredStringLength, bool $noSpecialChars): string
    {
        $requiredStringLength = max($requiredStringLength, 1);
        $characterSets = [
            StringUtils::RANDOM_LOWER_CASE_LETTERS,
            StringUtils::RANDOM_UPPER_CASE_LETTERS,
            StringUtils::RANDOM_DIGITS,
        ];
        if (!$noSpecialChars) {
            $characterSets[] = StringUtils::RANDOM_SPECIAL_CHARACTERS;
        }
        $allCharacters = implode(separator: StringUtils::IMPLODE_DEFAULT_SEPARATOR, array: $characterSets);

        $characters = '';
        foreach ($characterSets as $characterSet) {
            $characters .= StringUtils::pickRandomCharacter(characters: $characterSet);
        }
        while (strlen(string: $characters) < $requiredStringLength) {
            $characters .= StringUtils::pickRandomCharacter(characters: $allCharacters);
        }

        return substr(
            string: StringUtils::shuffleSecurely(characters: $characters),
            offset: 0,
            length: $requiredStringLength,
        );
    }

    public static function generateSalt(int $length = 16): string
    {
        $charactersLength = mb_strlen(string: StringUtils::SALT_CHARACTERS);
        $salt = '';
        for ($index = 0; $index < $length; $index++) {
            $salt .= mb_substr(
                string: StringUtils::SALT_CHARACTERS,
                start: random_int(min: 0, max: $charactersLength - 1),
                length: 1,
            );
        }

        return $salt;
    }

    /**
     * @param callable(string): (string|false) $convert
     */
    private static function convertEmailDomain(string $email, callable $convert): string
    {
        $fragments = explode(separator: '@', string: $email);
        $domain = array_pop(array: $fragments);
        $convertedDomain = $domain === '' ? false : $convert($domain);
        if ($convertedDomain === false) {
            throw new InvalidArgumentException(
                message: 'The domain "' . $domain . '" of the email address cannot be converted.',
            );
        }

        return implode(separator: '@', array: $fragments) . '@' . $convertedDomain;
    }

    /**
     * @param non-empty-string $characters
     *
     * @return non-empty-string
     */
    private static function pickRandomCharacter(string $characters): string
    {
        return $characters[random_int(min: 0, max: strlen(string: $characters) - 1)];
    }

    /**
     * Fisher-Yates shuffle with random_int (str_shuffle() uses a predictable generator).
     */
    private static function shuffleSecurely(string $characters): string
    {
        for ($index = strlen(string: $characters) - 1; $index > 0; $index--) {
            $otherIndex = random_int(min: 0, max: $index);
            $current = $characters[$index];
            $characters[$index] = $characters[$otherIndex];
            $characters[$otherIndex] = $current;
        }

        return $characters;
    }
}
