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
 * Class "Validator" is a "helper class"
 */
class Validator
{
    public static function stringWithoutWhitespaces(string $input): bool
    {
        return (preg_match(pattern: '#\s#', subject: $input) === 0);
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
        return IpValidator::validate(input: $input, ipType: IpTypeEnum::ip);
    }

    public static function ipv4(mixed $input): bool
    {
        return IpValidator::validate(input: $input, ipType: IpTypeEnum::ipv4);
    }

    public static function ipv6(mixed $input): bool
    {
        return IpValidator::validate(input: $input, ipType: IpTypeEnum::ipv6);
    }
}