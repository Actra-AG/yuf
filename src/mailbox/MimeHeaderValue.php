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

    /**
     * The parameter decoded to UTF-8: RFC 2231 (`name*=utf-8''f%C3%BCr.pdf`, also continued as `name*0*=`, `name*1*=`)
     * before RFC 2047 encoded-words in the plain parameter (`name="=?UTF-8?Q?f=C3=BCr.pdf?="`, sent by many clients).
     *
     * @return string|null `null` if the parameter is missing
     */
    public function getDecodedParameter(string $name): ?string
    {
        if (array_key_exists(key: $name . '*', array: $this->parameters)) {
            return MimeHeaderValue::decodeExtendedValue(value: $this->parameters[$name . '*']);
        }
        $continued = $this->joinContinuations(name: $name);
        if ($continued !== null) {
            return $continued;
        }
        if (array_key_exists(key: $name, array: $this->parameters)) {
            return MimeHeaderDecoder::decode(value: $this->parameters[$name]);
        }

        return null;
    }

    public function isMultipart(): bool
    {
        return str_starts_with(haystack: $this->value, needle: 'multipart/');
    }

    /**
     * RFC 2231 section 3: `name*0`, `name*1*`, … joined in order; the encoded segments (`*` at the end) are percent
     * decoded, the charset of the first segment applies to the whole value.
     */
    private function joinContinuations(string $name): ?string
    {
        $bytes = '';
        $charset = '';
        for ($index = 0; ; $index++) {
            $key = $name . '*' . $index;
            if (array_key_exists(key: $key . '*', array: $this->parameters)) {
                $segment = $this->parameters[$key . '*'];
                if ($index === 0) {
                    [$charset, $segment] = MimeHeaderValue::splitCharset(value: $segment);
                }
                $bytes .= rawurldecode(string: $segment);

                continue;
            }
            if (array_key_exists(key: $key, array: $this->parameters)) {
                $bytes .= $this->parameters[$key];

                continue;
            }

            return $index === 0 ? null : MimeCharsetConverter::toUtf8(text: $bytes, charset: $charset);
        }
    }

    /**
     * RFC 2231 section 4: `charset'language'percent-encoded`.
     */
    private static function decodeExtendedValue(string $value): string
    {
        [$charset, $encoded] = MimeHeaderValue::splitCharset(value: $value);

        return MimeCharsetConverter::toUtf8(text: rawurldecode(string: $encoded), charset: $charset);
    }

    /**
     * @return array{string, string} the charset (empty if missing) and the value without charset and language
     */
    private static function splitCharset(string $value): array
    {
        $parts = explode(separator: "'", string: $value, limit: 3);

        return count(value: $parts) === 3 ? [$parts[0], $parts[2]] : ['', $value];
    }
}
