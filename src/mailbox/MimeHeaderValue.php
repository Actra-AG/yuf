<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

/**
 * A header value with parameters, e.g. `Content-Type: text/plain; charset="UTF-8"` or `Content-Disposition:
 * attachment; filename=a.txt` (RFC 2045 section 5.1): the value and the parameters in lower case names.
 *
 * @internal
 */
final readonly class MimeHeaderValue
{
    /**
     * @param string                $value      lower case, e.g. `text/plain` or `attachment`
     * @param array<string, string> $parameters by lower case name, quotes removed
     */
    private function __construct(
        public string $value,
        public array $parameters,
    ) {}

    /**
     * @param string $default the value if the header is missing or empty (RFC 2045: `text/plain`)
     */
    public static function fromHeader(?string $header, string $default): MimeHeaderValue
    {
        $header ??= '';
        $semicolonPosition = strpos(haystack: $header, needle: ';');
        $value = $semicolonPosition === false
            ? $header
            : substr(string: $header, offset: 0, length: $semicolonPosition);
        $value = strtolower(string: trim(string: $value));
        $parameters = [];
        $parameterPattern = '/;\s*([^\s=;]+)\s*=\s*(?:"((?:[^"\\\\]|\\\\.)*)"|([^\s;]*))/';
        preg_match_all(
            pattern: $parameterPattern,
            subject: $header,
            matches: $matches,
            flags: PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL,
        );
        foreach ($matches as $match) {
            $parameters[strtolower(string: $match[1])] = $match[3]
                ?? preg_replace(pattern: '/\\\\(.)/', replacement: '$1', subject: $match[2] ?? '')
                ?? '';
        }

        return new MimeHeaderValue(value: $value === '' ? $default : $value, parameters: $parameters);
    }

    public function getParameter(string $name): string
    {
        return array_key_exists(key: $name, array: $this->parameters) ? $this->parameters[$name] : '';
    }

    public function isMultipart(): bool
    {
        return str_starts_with(haystack: $this->value, needle: 'multipart/');
    }
}
