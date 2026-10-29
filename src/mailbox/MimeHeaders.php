<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

/**
 * The headers of a message or a MIME part, unfolded (RFC 5322 section 2.2.3), by lower case name.
 *
 * @internal
 */
final readonly class MimeHeaders
{
    /**
     * @param array<string, list<string>> $values the raw values (not decoded) in the order of the message
     */
    private function __construct(public array $values) {}

    /**
     * @param string $headerBlock the lines before the first empty line, line breaks `\n`
     */
    public static function fromHeaderBlock(string $headerBlock): MimeHeaders
    {
        $unfolded = preg_replace(pattern: '/\n(?=[ \t])/', replacement: '', subject: $headerBlock) ?? $headerBlock;
        $values = [];
        foreach (explode(separator: "\n", string: $unfolded) as $line) {
            $colonPosition = strpos(haystack: $line, needle: ':');
            if ($colonPosition === false || $colonPosition === 0) {
                continue;
            }
            $name = strtolower(string: rtrim(string: substr(string: $line, offset: 0, length: $colonPosition)));
            if (preg_match(pattern: '/^[\x21-\x39\x3B-\x7E]+$/D', subject: $name) !== 1) {
                continue;
            }
            $values[$name][] = trim(string: substr(string: $line, offset: $colonPosition + 1), characters: " \t");
        }

        return new MimeHeaders(values: $values);
    }

    public function first(string $name): ?string
    {
        return array_key_exists(key: $name, array: $this->values) ? array_first(array: $this->values[$name]) : null;
    }

    /**
     * @return array<string, list<string>> the values decoded to UTF-8 (RFC 2047)
     */
    public function decoded(): array
    {
        return array_map(
            callback: static fn(array $values): array => array_map(
                callback: MimeHeaderDecoder::decode(...),
                array: $values,
            ),
            array: $this->values,
        );
    }
}
