<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   Apache-2.0
 */

declare(strict_types=1);

/**
 * Adapted from libphonenumber-for-php (https://github.com/giggsey/libphonenumber-for-php), a port of libphonenumber
 * (https://github.com/google/libphonenumber), see LICENSE and NOTICE in src/phone/. Changed by Actra AG: reduced to
 * parsing, formatting and validation, rewritten to PHP 8.5 and the Actra coding standard (see NOTICE).
 *
 * @author Joshua Gigg and contributors (libphonenumber-for-php)
 * @author The Libphonenumber Authors (libphonenumber)
 */

namespace actra\yuf\phone;

use RuntimeException;

/**
 * Regular expression matcher with the semantics of the Java `Matcher` the libphonenumber code is written for. The
 * patterns come from the phone number metadata, never from user input.
 *
 * @internal
 */
final class PhoneMatcher
{
    private readonly string $pattern;
    /** @var list<array{string, int}> the matched text and the character offset of every group */
    private array $groups = [];

    public function __construct(string $pattern, private readonly string $subject)
    {
        $this->pattern = str_replace(search: '/', replace: '\/', subject: $pattern);
    }

    /**
     * The pattern matches somewhere in the subject.
     */
    public function find(): bool
    {
        return $this->match(flags: 'ui');
    }

    /**
     * The pattern matches at the start of the subject.
     */
    public function lookingAt(): bool
    {
        return $this->match(flags: 'uAi');
    }

    /**
     * The pattern matches the whole subject, also if an alternative that matches only the start of the subject comes
     * first (`\d|\d\d` matches `12`): the alternatives are tried until the whole subject matches, as in Java.
     */
    public function matches(): bool
    {
        return $this->match(flags: 'uAi', patternPrefix: '(?:', patternSuffix: ')\\z');
    }

    public function start(): ?int
    {
        return $this->groups === [] ? null : $this->groups[0][1];
    }

    public function end(): ?int
    {
        return $this->groups === [] ? null : ($this->groups[0][1] + mb_strlen(string: $this->groups[0][0]));
    }

    public function group(int $group): ?string
    {
        return array_key_exists(key: $group, array: $this->groups) ? $this->groups[$group][0] : null;
    }

    public function groupCount(): ?int
    {
        return $this->groups === [] ? null : (count(value: $this->groups) - 1);
    }

    public function replaceFirst(string $replacement): string
    {
        return $this->replace(replacement: $replacement, limit: 1);
    }

    public function replaceAll(string $replacement): string
    {
        return $this->replace(replacement: $replacement, limit: -1);
    }

    private function match(string $flags, string $patternPrefix = '', string $patternSuffix = ''): bool
    {
        $this->groups = [];
        $groups = [];
        $result = preg_match(
            pattern: '/' . $patternPrefix . $this->pattern . $patternSuffix . '/' . $flags,
            subject: $this->subject,
            matches: $groups,
            flags: PREG_OFFSET_CAPTURE,
        );
        if ($result !== 1) {
            return false;
        }
        foreach ($groups as $group) {
            $this->groups[] = [
                $group[0],
                mb_strlen(string: mb_strcut(string: $this->subject, start: 0, length: $group[1])),
            ];
        }

        return true;
    }

    private function replace(string $replacement, int $limit): string
    {
        $result = preg_replace(
            pattern: '/' . $this->pattern . '/x',
            replacement: $replacement,
            subject: $this->subject,
            limit: $limit,
        );
        if ($result === null) {
            throw new RuntimeException(message: 'Replacing in a phone number failed: ' . preg_last_error_msg());
        }

        return $result;
    }
}
