<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\datacheck\validatorTypes;

use InvalidArgumentException;

class IpValidator
{
    public static function validate(string $input, IpTypeEnum $ipType): bool
    {
        $filterFlags = match ($ipType) {
            IpTypeEnum::ipv4 => FILTER_FLAG_IPV4,
            IpTypeEnum::ipv6 => FILTER_FLAG_IPV6,
            default => ['flags' => null],
        };

        return (filter_var(value: $input, filter: FILTER_VALIDATE_IP, options: $filterFlags) !== false);
    }

    /**
     * Whether the IP address is one of the addresses or in one of the ranges of the whitelist.
     *
     * @param array<string> $whiteList IPv4 and IPv6 addresses and ranges in CIDR notation (`192.168.1.0/24`,
     *     `2001:db8::/32`); IPv4 ranges may be shortened (`10/8` is `10.0.0.0/8`)
     *
     * @throws InvalidArgumentException if a range of the whitelist is invalid
     */
    public static function isInWhitelist(array $whiteList, string $ipAddressToCheck): bool
    {
        $addressBytes = IpValidator::toBytes(ipAddress: $ipAddressToCheck);
        foreach ($whiteList as $whitelistItem) {
            if ($whitelistItem === $ipAddressToCheck) {
                return true;
            }
            if ($addressBytes === null) {
                continue;
            }
            if (!str_contains(haystack: $whitelistItem, needle: '/')) {
                if (IpValidator::toBytes(ipAddress: $whitelistItem) === $addressBytes) {
                    return true;
                }
                continue;
            }
            if (IpValidator::isInRange(range: $whitelistItem, addressBytes: $addressBytes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return ?string The binary form of the address (4 bytes for IPv4, 16 bytes for IPv6), null if it is no IP address
     */
    private static function toBytes(string $ipAddress): ?string
    {
        if (filter_var(value: $ipAddress, filter: FILTER_VALIDATE_IP) === false) {
            return null;
        }
        $bytes = inet_pton(ip: $ipAddress);

        return $bytes === false ? null : $bytes;
    }

    private static function isInRange(string $range, string $addressBytes): bool
    {
        [$network, $prefixLength] = explode(separator: '/', string: $range, limit: 2);
        if (preg_match(pattern: '/^\d+(\.\d+){0,2}$/', subject: $network) === 1) {
            // Shortened IPv4 notation: "10" is "10.0.0.0"
            $network .= str_repeat(string: '.0', times: 3 - substr_count(haystack: $network, needle: '.'));
        }
        $networkBytes = IpValidator::toBytes(ipAddress: $network);
        if ($networkBytes === null || preg_match(pattern: '/^\d{1,3}$/', subject: $prefixLength) !== 1) {
            throw new InvalidArgumentException(message: 'Invalid IP range in the whitelist: ' . $range);
        }
        $prefixBits = (int) $prefixLength;
        if ($prefixBits > strlen(string: $networkBytes) * 8) {
            throw new InvalidArgumentException(message: 'Invalid IP range in the whitelist: ' . $range);
        }
        if (strlen(string: $addressBytes) !== strlen(string: $networkBytes)) {
            // IPv4 address and IPv6 range, or vice versa
            return false;
        }
        $fullBytes = intdiv(num1: $prefixBits, num2: 8);
        if (substr(string: $addressBytes, offset: 0, length: $fullBytes)
            !== substr(string: $networkBytes, offset: 0, length: $fullBytes)) {
            return false;
        }
        $remainingBits = $prefixBits % 8;
        if ($remainingBits === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        $addressByte = ord(character: $addressBytes[$fullBytes]);
        $networkByte = ord(character: $networkBytes[$fullBytes]);

        return ($addressByte & $mask) === ($networkByte & $mask);
    }
}
