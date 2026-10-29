<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

/**
 * Decodes the encoded-words of RFC 2047 (`=?UTF-8?Q?...?=`, `=?ISO-8859-1?B?...?=`) in an unfolded header value to
 * UTF-8. Whitespace between two encoded-words is removed, adjacent words of the same charset are decoded together (so a
 * character split over two words stays intact). Text that is not encoded is converted like a body without charset.
 *
 * @internal
 */
final class MimeHeaderDecoder
{
    private const string ENCODED_WORD_PATTERN = '/=\?([^?\s*]+)(?:\*[^?\s]*)?\?([QqBb])\?([^?\s]*)\?=/';

    public static function decode(string $value): string
    {
        preg_match_all(
            pattern: MimeHeaderDecoder::ENCODED_WORD_PATTERN,
            subject: $value,
            matches: $matches,
            flags: PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );
        $decoded = '';
        $position = 0;
        $pendingCharset = '';
        $pendingBytes = '';
        foreach ($matches as $match) {
            [$word, $offset] = $match[0];
            $text = substr(string: $value, offset: $position, length: $offset - $position);
            $isBetweenWords = $position > 0 && trim(string: $text, characters: " \t") === '';
            $charset = strtolower(string: $match[1][0]);
            if (!$isBetweenWords || $charset !== $pendingCharset) {
                $decoded .= MimeCharsetConverter::toUtf8(text: $pendingBytes, charset: $pendingCharset);
                $decoded .= $isBetweenWords ? '' : MimeCharsetConverter::toUtf8(text: $text, charset: '');
                $pendingBytes = '';
                $pendingCharset = $charset;
            }
            $pendingBytes .= MimeHeaderDecoder::decodeWord(
                isBase64: strtoupper(string: $match[2][0]) === 'B',
                data: $match[3][0],
            );
            $position = $offset + strlen(string: $word);
        }

        return $decoded
            . MimeCharsetConverter::toUtf8(text: $pendingBytes, charset: $pendingCharset)
            . MimeCharsetConverter::toUtf8(text: substr(string: $value, offset: $position), charset: '');
    }

    private static function decodeWord(bool $isBase64, string $data): string
    {
        if (!$isBase64) {
            return quoted_printable_decode(string: str_replace(search: '_', replace: ' ', subject: $data));
        }
        $bytes = base64_decode(string: $data, strict: true);

        return $bytes === false ? '' : $bytes;
    }
}
