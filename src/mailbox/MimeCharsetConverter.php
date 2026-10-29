<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

/**
 * Converts text of a message from its declared charset to UTF-8. A missing or unknown charset is read as UTF-8 if the
 * bytes are valid UTF-8, otherwise as Windows-1252; ISO-8859-1 is read as Windows-1252 (its superset, as browsers do).
 * Invalid bytes never reach the result.
 *
 * @internal
 */
final class MimeCharsetConverter
{
    private const string FALLBACK_CHARSET = 'Windows-1252';

    /** mbstring encodings that are no charsets (transfer encodings, entities) */
    private const array NO_CHARSETS = [
        'pass',
        'base64',
        'uuencode',
        'html-entities',
        'quoted-printable',
        '7bit',
        '8bit',
    ];

    public static function toUtf8(string $text, string $charset): string
    {
        $charset = strtolower(string: trim(string: $charset, characters: " \t\"'"));
        if (in_array(needle: $charset, haystack: ['', 'utf-8', 'utf8', 'us-ascii', 'ascii'], strict: true)) {
            return MimeCharsetConverter::fromUnknown(text: $text);
        }
        if (in_array(needle: $charset, haystack: ['iso-8859-1', 'latin1', 'latin-1'], strict: true)) {
            $charset = strtolower(string: MimeCharsetConverter::FALLBACK_CHARSET);
        }
        $mbEncoding = MimeCharsetConverter::findMbEncoding(charset: $charset);
        if ($mbEncoding === null) {
            return MimeCharsetConverter::fromUnknown(text: $text);
        }

        $converted = mb_convert_encoding(string: $text, to_encoding: 'UTF-8', from_encoding: $mbEncoding);

        return $converted === false ? '' : mb_scrub(string: $converted, encoding: 'UTF-8');
    }

    private static function fromUnknown(string $text): string
    {
        if (mb_check_encoding(value: $text, encoding: 'UTF-8')) {
            return $text;
        }

        return mb_convert_encoding(
            string: $text,
            to_encoding: 'UTF-8',
            from_encoding: MimeCharsetConverter::FALLBACK_CHARSET,
        );
    }

    /**
     * @return string|null the name of the encoding in mbstring, `null` if mbstring does not know the charset
     */
    private static function findMbEncoding(string $charset): ?string
    {
        foreach (mb_list_encodings() as $encoding) {
            $isNoCharset = in_array(
                needle: strtolower(string: $encoding),
                haystack: MimeCharsetConverter::NO_CHARSETS,
                strict: true,
            );
            if ($isNoCharset) {
                continue;
            }
            $names = [$encoding, ...mb_encoding_aliases(encoding: $encoding)];
            foreach ($names as $name) {
                if (strtolower(string: $name) === $charset) {
                    return $encoding;
                }
            }
        }

        return null;
    }
}
