<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

/**
 * Escapes values for HTML text and for quoted attribute values (UTF-8, invalid byte sequences become U+FFFD).
 *
 * Stays static on purpose: pure functions of their argument without state or dependencies.
 */
final readonly class HtmlEncoder
{
    /**
     * Encodes `&`, `<`, `>` and both kinds of quotes: safe for HTML text and for quoted attribute values.
     */
    public static function encode(string|float|int|bool|null $value): string
    {
        if ($value === null) {
            return '';
        }

        return htmlspecialchars(
            string: (string) $value,
            flags: ENT_QUOTES | ENT_SUBSTITUTE,
            encoding: 'UTF-8',
        );
    }

    /**
     * Encodes `&`, `<` and `>` only. Only for the content of an element (text between tags), never for attribute
     * values: a `"` in the value would end the attribute. Use `encode()` when in doubt.
     */
    public static function encodeKeepQuotes(string|float|int|bool|null $value): string
    {
        if ($value === null) {
            return '';
        }

        // The standard forbids ENT_NOQUOTES because of attributes; element content is the one place where the quotes
        // are meant to stay as they are
        return htmlspecialchars( // @phpstan-ignore disallowed.function
            string: (string) $value,
            flags: ENT_NOQUOTES | ENT_SUBSTITUTE,
            encoding: 'UTF-8',
        );
    }
}
