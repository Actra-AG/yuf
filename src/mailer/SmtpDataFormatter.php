<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

/**
 * Prepares the message for the `DATA` command (RFC 5321 section 4.5.2): lines of at most 998 characters and dot
 * stuffing.
 *
 * Static on purpose: pure function without state.
 *
 * @internal
 */
final readonly class SmtpDataFormatter
{
    /**
     * @return list<string> the lines without line breaks; a line that starts with a dot has one more dot
     */
    public static function toLines(string $data): array
    {
        // Normalize line breaks before splitting
        $lines = explode(separator: "\n", string: str_replace(search: ["\r\n", "\r"], replace: "\n", subject: $data));

        $inHeaders = SmtpDataFormatter::startsWithHeader(firstLine: $lines[0]);
        $linesOut = [];
        foreach ($lines as $line) {
            if ($inHeaders && $line === '') {
                $inHeaders = false;
            }
            foreach (SmtpDataFormatter::splitLongLine(line: $line, inHeaders: $inHeaders) as $lineOut) {
                $linesOut[] = str_starts_with(haystack: $lineOut, needle: '.') ? '.' . $lineOut : $lineOut;
            }
        }

        return $linesOut;
    }

    /**
     * A complete message (not only a body) starts with a header: a field name without space before the first colon.
     */
    private static function startsWithHeader(string $firstLine): bool
    {
        $colonPosition = strpos(haystack: $firstLine, needle: ':');

        return $colonPosition !== false
            && $colonPosition > 0
            && !str_contains(haystack: substr(string: $firstLine, offset: 0, length: $colonPosition), needle: ' ');
    }

    /**
     * Breaks a line that is too long at a space, or hard if it has none. The continuation lines of a header start
     * with a tab (RFC 822 section 3.1.1).
     *
     * @return non-empty-list<string>
     */
    private static function splitLongLine(string $line, bool $inHeaders): array
    {
        $parts = [];
        while (strlen(string: $line) > MailerConstants::MAX_LINE_LENGTH) {
            // Working backwards, try to find a space to avoid breaking in the middle of a word
            $position = strrpos(
                haystack: substr(string: $line, offset: 0, length: MailerConstants::MAX_LINE_LENGTH),
                needle: ' ',
            );
            if ($position === false || $position === 0) {
                // No nice break found, add a hard break
                $position = MailerConstants::MAX_LINE_LENGTH - 1;
                $parts[] = substr(string: $line, offset: 0, length: $position);
                $line = substr(string: $line, offset: $position);
            } else {
                $parts[] = substr(string: $line, offset: 0, length: $position);
                // The space is dropped
                $line = substr(string: $line, offset: $position + 1);
            }
            if ($inHeaders) {
                $line = "\t" . $line;
            }
        }
        $parts[] = $line;

        return $parts;
    }
}
