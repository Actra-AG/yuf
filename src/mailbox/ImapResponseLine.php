<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

/**
 * One response of the server (RFC 9051 section 7) with its literals: `* 1 FETCH (UID 7 BODY[] {12}` + 12 bytes + `)`
 * has the segments `* 1 FETCH (UID 7 BODY[] {12}` and `)` and one literal; `$segments[$i]` is followed by
 * `$literals[$i]`.
 *
 * @internal
 */
final readonly class ImapResponseLine
{
    /**
     * @param list<string> $segments the text without line breaks
     * @param list<string> $literals
     */
    public function __construct(
        public array $segments,
        public array $literals,
    ) {}

    /**
     * @return string the segments separated by a space, without the literals
     */
    public function getText(): string
    {
        return implode(separator: ' ', array: $this->segments);
    }

    /**
     * @return string|null the literal that directly follows the text matching `$pattern` (anchored at the end of a
     *                     segment), `null` if there is none
     */
    public function findLiteralAfter(string $pattern): ?string
    {
        foreach ($this->segments as $index => $segment) {
            if (
                array_key_exists(key: $index, array: $this->literals)
                && preg_match(pattern: '/' . $pattern . ' \{\d+\+?\}$/Di', subject: $segment) === 1
            ) {
                return $this->literals[$index];
            }
        }

        return null;
    }
}
