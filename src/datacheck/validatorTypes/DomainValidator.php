<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\datacheck\validatorTypes;

use actra\yuf\datacheck\Validator;

/**
 * Whether a text is a domain name: at least a name and a listed top-level domain (`TldValidator`), labels of letters,
 * digits and hyphens (IDN names are checked in their punycode form, so `münchen.de` and `xn--mnchen-3ya.de` are both
 * valid), at most 63 characters per label and 253 in total. No whitespace, no trailing dot, no IP addresses.
 */
final readonly class DomainValidator
{
    /** Name + '.' + top-level domain: at least 5 characters */
    private const int MIN_LENGTH = 5;

    public static function validate(string $input): bool
    {
        if (
            !Validator::stringWithoutWhitespaces(input: $input)
            || mb_strlen(string: $input) < DomainValidator::MIN_LENGTH
        ) {
            return false;
        }
        $encodedDomain = idn_to_ascii(domain: $input);
        if ($encodedDomain === false) {
            return false;
        }
        $labels = explode(separator: '.', string: $encodedDomain);
        if (count(value: $labels) < 2 || !TldValidator::validate(input: array_last(array: $labels))) {
            return false;
        }

        return filter_var(
            value: $encodedDomain,
            filter: FILTER_VALIDATE_DOMAIN,
            options: FILTER_FLAG_HOSTNAME,
        ) !== false;
    }
}
