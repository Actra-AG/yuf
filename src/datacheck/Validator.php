<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\datacheck;

use actra\yuf\datacheck\validatorTypes\DomainValidator;
use actra\yuf\datacheck\validatorTypes\IpTypeEnum;
use actra\yuf\datacheck\validatorTypes\IpValidator;
use actra\yuf\datacheck\validatorTypes\TldValidator;

/**
 * The validators of the typed classes in one place. Stays a static class: every method is a pure function of its
 * argument without state.
 */
final readonly class Validator
{
    /**
     * Whether the input contains none of the ASCII whitespace characters (space, tab, line breaks, vertical tab and
     * form feed); Unicode spaces such as the no-break space are not whitespace here.
     */
    public static function stringWithoutWhitespaces(string $input): bool
    {
        return preg_match(pattern: '#\s#', subject: $input) === 0;
    }

    public static function domain(string $input): bool
    {
        return DomainValidator::validate(input: $input);
    }

    public static function tld(string $input): bool
    {
        return TldValidator::validate(input: $input);
    }

    public static function ip(string $input): bool
    {
        return IpValidator::validate(input: $input, ipType: IpTypeEnum::IP);
    }

    public static function ipv4(string $input): bool
    {
        return IpValidator::validate(input: $input, ipType: IpTypeEnum::IPV4);
    }

    public static function ipv6(string $input): bool
    {
        return IpValidator::validate(input: $input, ipType: IpTypeEnum::IPV6);
    }
}
