<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\datacheck\validatorTypes;

use InvalidArgumentException;

/**
 * Validates IP addresses and checks them against whitelists. Stays a static class: every method is a pure function of
 * its arguments without state.
 */
final readonly class IpValidator
{
    /** The first 12 bytes of an IPv4-mapped IPv6 address: ten zero bytes and `ffff` (`::ffff:0:0/96`) */
    private const string MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

    public static function validate(string $input, IpTypeEnum $ipType): bool
    {
        $filterFlags = match ($ipType) {
            IpTypeEnum::IP => FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6,
            IpTypeEnum::IPV4 => FILTER_FLAG_IPV4,
            IpTypeEnum::IPV6 => FILTER_FLAG_IPV6,
        };

        return filter_var(value: $input, filter: FILTER_VALIDATE_IP, options: $filterFlags) !== false;
    }

    /**
     * Whether the IP address is one of the addresses or in one of the ranges of the whitelist. Fails closed: an
     * address that is no valid IP address is never in the whitelist, whatever the whitelist contains. An IPv4-mapped
     * IPv6 address (`::ffff:192.0.2.1`, `::ffff:c000:201`; exactly the range `::ffff:0:0/96`) is the same address as
     * the IPv4 address: it matches IPv4 entries, and an IPv4 address matches entries written as mapped address or
     * range (`::ffff:192.0.2.0/120` is `192.0.2.0/24`; `::ffff:0:0/96` is all IPv4 addresses). A mapped address
     * still matches the IPv6 ranges that contain it (`::/0`). Other IPv6 addresses are never taken as IPv4:
     * not the deprecated IPv4-compatible `::192.0.2.1` and not the NAT64 range `64:ff9b::/96`.
     *
     * @param array<string> $whiteList IPv4 and IPv6 addresses and ranges in CIDR notation (`192.0.2.0/24`,
     *     `2001:db8::/32`); IPv4 ranges may be shortened (`10/8` is `10.0.0.0/8`). An entry that is neither a valid
     *     address nor a range matches nothing.
     *
     * @throws InvalidArgumentException if a range of the whitelist is invalid (checked when the loop reaches it)
     */
    public static function isInWhitelist(array $whiteList, string $ipAddressToCheck): bool
    {
        $addressBytes = IpValidator::toBytes(ipAddress: $ipAddressToCheck);
        if ($addressBytes === null) {
            return false;
        }
        $ipv4Bytes = IpValidator::mappedToIpv4(bytes: $addressBytes);
        foreach ($whiteList as $whitelistItem) {
            if (!str_contains(haystack: $whitelistItem, needle: '/')) {
                $itemBytes = IpValidator::toBytes(ipAddress: $whitelistItem);
                if ($itemBytes !== null && IpValidator::mappedToIpv4(bytes: $itemBytes) === $ipv4Bytes) {
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

    /**
     * @return string The 4 bytes of the IPv4 address if the bytes are an IPv4-mapped IPv6 address (`::ffff:0:0/96`),
     *                otherwise the bytes unchanged
     */
    private static function mappedToIpv4(string $bytes): string
    {
        if (strlen(string: $bytes) === 16 && str_starts_with(haystack: $bytes, needle: IpValidator::MAPPED_PREFIX)) {
            return substr(string: $bytes, offset: 12);
        }

        return $bytes;
    }

    /**
     * @throws InvalidArgumentException if the range is invalid
     */
    private static function isInRange(string $range, string $addressBytes): bool
    {
        $parts = explode(separator: '/', string: $range);
        if (count(value: $parts) !== 2) {
            throw new InvalidArgumentException(message: 'Invalid IP range in the whitelist: ' . $range);
        }
        [$network, $prefixLength] = $parts;
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
        $ipv4NetworkBytes = IpValidator::mappedToIpv4(bytes: $networkBytes);
        if ($ipv4NetworkBytes !== $networkBytes && $prefixBits >= 96) {
            // A mapped range is an IPv4 range: ::ffff:192.0.2.0/120 is 192.0.2.0/24
            $networkBytes = $ipv4NetworkBytes;
            $prefixBits -= 96;
        }
        foreach ([$addressBytes, IpValidator::mappedToIpv4(bytes: $addressBytes)] as $candidateBytes) {
            if (
                strlen(string: $candidateBytes) === strlen(string: $networkBytes)
                && IpValidator::bytesShareThePrefix(
                    addressBytes: $candidateBytes,
                    networkBytes: $networkBytes,
                    prefixBits: $prefixBits,
                )
            ) {
                return true;
            }
        }

        // IPv4 address and IPv6 range, or vice versa
        return false;
    }

    private static function bytesShareThePrefix(string $addressBytes, string $networkBytes, int $prefixBits): bool
    {
        $fullBytes = intdiv(num1: $prefixBits, num2: 8);
        if (
            substr(string: $addressBytes, offset: 0, length: $fullBytes)
            !== substr(string: $networkBytes, offset: 0, length: $fullBytes)
        ) {
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
