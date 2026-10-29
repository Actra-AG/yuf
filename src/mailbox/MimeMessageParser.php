<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

use actra\yuf\mailer\MailerAddress;
use actra\yuf\mailer\MailerException;
use DateTimeImmutable;

/**
 * Parses a raw message (RFC 5322, MIME) into a `ReceivedMail`, without network or file access. Malformed messages
 * never throw: what cannot be read is `null` or empty.
 *
 * The text body comes from the `text/plain` parts (`multipart/mixed`: all inline ones, `multipart/alternative`: the
 * plain one); without such a part from the `text/html` part, converted to text. Attachments are ignored.
 */
final class MimeMessageParser
{
    /** Nested multiparts deeper than this are ignored (protection against crafted messages) */
    private const int MAX_DEPTH = 20;

    /** Formats of the `Date` header (RFC 5322 section 3.3, with obsolete zone names), the day name is optional */
    private const array DATE_FORMATS = ['j M Y H:i:s O', 'j M Y H:i O', 'j M Y H:i:s T', 'j M Y H:i T'];

    public function parse(string $rawMessage): ReceivedMail
    {
        $message = str_replace(search: ["\r\n", "\r"], replace: "\n", subject: $rawMessage);
        [$headerBlock, $body] = MimeMessageParser::splitHeaderAndBody(part: $message);
        $headers = MimeHeaders::fromHeaderBlock(headerBlock: $headerBlock);
        $messageId = $headers->first(name: 'message-id');

        return new ReceivedMail(
            messageId: $messageId === null || $messageId === '' ? null : $messageId,
            date: MimeMessageParser::parseDate(value: $headers->first(name: 'date')),
            from: MimeMessageParser::parseFrom(value: $headers->first(name: 'from')),
            subject: MimeHeaderDecoder::decode(value: $headers->first(name: 'subject') ?? ''),
            headers: $headers->decoded(),
            textBody: MimeMessageParser::textOf(headers: $headers, body: $body, depth: 0),
            rawMessage: $rawMessage,
        );
    }

    /**
     * @return array{string, string} the header block and the body
     */
    private static function splitHeaderAndBody(string $part): array
    {
        if (str_starts_with(haystack: $part, needle: "\n")) {
            return ['', substr(string: $part, offset: 1)];
        }
        $position = strpos(haystack: $part, needle: "\n\n");
        if ($position === false) {
            return [$part, ''];
        }

        return [substr(string: $part, offset: 0, length: $position), substr(string: $part, offset: $position + 2)];
    }

    private static function textOf(MimeHeaders $headers, string $body, int $depth): string
    {
        $disposition = MimeHeaderValue::fromHeader(header: $headers->first(name: 'content-disposition'), default: '');
        if ($disposition->value === 'attachment' || $depth > MimeMessageParser::MAX_DEPTH) {
            return '';
        }
        $contentType = MimeMessageParser::contentTypeOf(headers: $headers);
        if ($contentType->isMultipart()) {
            return MimeMessageParser::textOfMultipart(contentType: $contentType, body: $body, depth: $depth);
        }
        if ($contentType->value !== 'text/plain' && $contentType->value !== 'text/html') {
            return '';
        }
        $text = MimeCharsetConverter::toUtf8(
            text: MimeMessageParser::decodeTransferEncoding(
                body: $body,
                encoding: strtolower(string: trim(string: $headers->first(name: 'content-transfer-encoding') ?? '')),
            ),
            charset: $contentType->getParameter(name: 'charset'),
        );
        if ($contentType->value === 'text/html') {
            return HtmlTextConverter::convert(html: $text);
        }

        return trim(string: str_replace(search: ["\r\n", "\r"], replace: "\n", subject: $text), characters: "\n");
    }

    private static function contentTypeOf(MimeHeaders $headers): MimeHeaderValue
    {
        return MimeHeaderValue::fromHeader(header: $headers->first(name: 'content-type'), default: 'text/plain');
    }

    private static function textOfMultipart(MimeHeaderValue $contentType, string $body, int $depth): string
    {
        $parts = [];
        $boundary = $contentType->getParameter(name: 'boundary');
        foreach (MimeMessageParser::splitMultipart(body: $body, boundary: $boundary) as $part) {
            [$headerBlock, $partBody] = MimeMessageParser::splitHeaderAndBody(part: $part);
            $parts[] = [MimeHeaders::fromHeaderBlock(headerBlock: $headerBlock), $partBody];
        }
        if ($contentType->value === 'multipart/alternative') {
            return MimeMessageParser::textOfAlternative(parts: $parts, depth: $depth);
        }
        $texts = [];
        foreach ($parts as [$partHeaders, $partBody]) {
            $text = MimeMessageParser::textOf(headers: $partHeaders, body: $partBody, depth: $depth + 1);
            if ($text !== '') {
                $texts[] = $text;
            }
            if ($contentType->value === 'multipart/related') {
                break; // The first part is the document, the others are its images
            }
        }

        return implode(separator: "\n\n", array: $texts);
    }

    /**
     * @param list<array{MimeHeaders, string}> $parts
     */
    private static function textOfAlternative(array $parts, int $depth): string
    {
        foreach ($parts as [$partHeaders, $partBody]) {
            if (MimeMessageParser::contentTypeOf(headers: $partHeaders)->value === 'text/plain') {
                $text = MimeMessageParser::textOf(headers: $partHeaders, body: $partBody, depth: $depth + 1);
                if ($text !== '') {
                    return $text;
                }
            }
        }
        foreach ($parts as [$partHeaders, $partBody]) {
            $text = MimeMessageParser::textOf(headers: $partHeaders, body: $partBody, depth: $depth + 1);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    /**
     * @return list<string> the parts between the boundary lines, without preamble and epilogue
     */
    private static function splitMultipart(string $body, string $boundary): array
    {
        if ($boundary === '') {
            return [];
        }
        $parts = [];
        $current = null;
        foreach (explode(separator: "\n", string: $body) as $line) {
            $trimmedLine = rtrim(string: $line, characters: " \t");
            if ($trimmedLine === '--' . $boundary . '--') {
                break;
            }
            if ($trimmedLine === '--' . $boundary) {
                if ($current !== null) {
                    $parts[] = $current;
                }
                $current = '';

                continue;
            }
            if ($current !== null) {
                $current .= $line . "\n";
            }
        }
        if ($current !== null) {
            $parts[] = $current;
        }

        // The line break before a boundary line belongs to the boundary (RFC 2046 section 5.1.1)
        return array_map(
            callback: static fn(string $part): string => str_ends_with(haystack: $part, needle: "\n")
                ? substr(string: $part, offset: 0, length: -1)
                : $part,
            array: $parts,
        );
    }

    private static function decodeTransferEncoding(string $body, string $encoding): string
    {
        return match ($encoding) {
            'quoted-printable' => quoted_printable_decode(string: $body),
            'base64' => MimeMessageParser::decodeBase64(body: $body),
            default => $body,
        };
    }

    private static function decodeBase64(string $body): string
    {
        $bytes = base64_decode(
            string: preg_replace(pattern: '/[^A-Za-z0-9+\/=]/', replacement: '', subject: $body) ?? '',
            strict: true,
        );

        return $bytes === false ? '' : $bytes;
    }

    private static function parseDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }
        // Without the day name and the comment, e.g. `Wed, 29 Oct 2026 10:15:00 +0100 (CET)`
        $value = preg_replace(pattern: '/^\s*[A-Za-z]{3},\s*|\s*\([^)]*\)\s*$/', replacement: '', subject: $value);
        $value = preg_replace(pattern: '/\s+/', replacement: ' ', subject: trim(string: $value ?? ''));
        foreach (MimeMessageParser::DATE_FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat(format: '!' . $format, datetime: $value ?? '');
            $errors = DateTimeImmutable::getLastErrors();
            if ($date !== false && $errors === false) {
                return $date;
            }
        }

        return null;
    }

    private static function parseFrom(?string $value): ?MailerAddress
    {
        if ($value === null) {
            return null;
        }
        $isNameAddr = preg_match(pattern: '/^(.*?)<([^<>]+)>/s', subject: $value, matches: $matches) === 1;
        // `addr-spec (comment)`: the address without the comment
        $firstAddress = explode(separator: ',', string: $value)[0];
        $email = $isNameAddr
            ? $matches[2]
            : trim(string: preg_replace(pattern: '/\([^)]*\)/', replacement: '', subject: $firstAddress) ?? '');
        $name = $isNameAddr ? trim(string: $matches[1], characters: " \t\"") : '';
        try {
            return MailerAddress::createFromAddress(
                inputEmail: $email,
                inputName: MimeHeaderDecoder::decode(value: str_replace(search: '\\"', replace: '"', subject: $name)),
            );
        } catch (MailerException) {
            return null; // An invalid sender is reported as missing
        }
    }
}
