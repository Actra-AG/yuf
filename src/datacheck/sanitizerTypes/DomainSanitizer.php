<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\datacheck\sanitizerTypes;

use RuntimeException;

/**
 * Reduces what people enter for a domain to the host: lower case, without zero-width characters, spaces, scheme, user
 * info (only after a scheme: `user@example.com` without scheme is kept as typed), port, path, query and fragment, and
 * without the `www.` prefix (`www.ch` stays, because `ch` alone would be a top-level domain). The result is not
 * validated (see `DomainValidator`).
 */
final readonly class DomainSanitizer
{
    /**
     * @throws RuntimeException if a regular expression fails (PCRE limit)
     */
    public static function sanitize(string $input): string
    {
        $domain = DomainSanitizer::replace(
            patterns: ['/\xE2\x80\x8B/', '/&#8203;/', '/ /'],
            subject: mb_strtolower(string: trim(string: $input)),
        );
        $hasScheme = preg_match(pattern: '~^[a-z][a-z0-9+.-]*://~', subject: $domain) === 1;
        $domain = DomainSanitizer::replace(patterns: ['~^[a-z][a-z0-9+.-]*://~'], subject: $domain);
        // The authority ends at the first slash, question mark or hash
        $authority = DomainSanitizer::replace(patterns: ['~[/?#].*$~s'], subject: $domain);
        if ($hasScheme) {
            $userInfoEnd = strrpos(haystack: $authority, needle: '@');
            $authority = $userInfoEnd === false ? $authority : substr(string: $authority, offset: $userInfoEnd + 1);
        }
        $host = DomainSanitizer::replace(patterns: ['~(?<=[^:]):\d*$~'], subject: $authority);
        $withoutWww = DomainSanitizer::replace(patterns: ['~^www\.~'], subject: $host);
        // "www.ch" has the prefix "www." and the name "ch": the name alone would be a top-level domain
        if ($withoutWww !== $host && !str_contains(haystack: $withoutWww, needle: '.')) {
            return $host;
        }

        return $withoutWww;
    }

    /**
     * @param list<string> $patterns
     *
     * @throws RuntimeException if a regular expression fails (PCRE limit)
     */
    private static function replace(array $patterns, string $subject): string
    {
        $result = preg_replace(pattern: $patterns, replacement: '', subject: $subject);
        if ($result === null) {
            throw new RuntimeException(message: 'Domain value is not valid: "' . $subject . '"');
        }

        return $result;
    }
}
