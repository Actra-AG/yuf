<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use InvalidArgumentException;
use Override;

/**
 * Exact hosts and one-label wildcards (`allowedDomains` of the environment settings). A pattern is
 * `[*.]<host name>[:<port>]` or an IP address (`127.0.0.1`, `[::1]`) with an optional port:
 *
 * - `*.example.ch` matches exactly one label in front of `example.ch` (`a.example.ch`), neither `example.ch` itself
 *   nor `a.b.example.ch`. The wildcard is only allowed as the whole first label of a name with at least two more.
 * - The port is compared literally: a pattern without port matches only a host without port.
 * - Hosts are compared in lower case. International names are written in punycode (`xn--…`).
 *
 * Pure: the patterns are checked once in the constructor, no regular expression is built from them.
 */
final readonly class HostPatterns implements HostPolicy
{
    private const string LABEL_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D';
    private const string PORT_PATTERN = '/^[1-9][0-9]{0,4}$/D';
    private const string HOST_CHARACTERS_PATTERN = '/^[a-z0-9.:\[\]-]+$/D';
    private const int MAX_NAME_LENGTH = 253;

    /** @var list<string> */
    private array $patterns;
    /** @var array<string, true> The exact hosts, with port */
    private array $exactHosts;
    /** @var list<array{suffix: string, port: ?string}> The wildcards: `.example.ch` and the port */
    private array $wildcards;

    /**
     * @param list<string> $patterns
     *
     * @throws InvalidArgumentException for an invalid pattern, naming it
     */
    public function __construct(array $patterns)
    {
        $normalizedPatterns = [];
        $exactHosts = [];
        $wildcards = [];
        foreach ($patterns as $pattern) {
            $normalizedPattern = strtolower(string: $pattern);
            $normalizedPatterns[] = $normalizedPattern;
            [$name, $port] = HostPatterns::splitPort(host: $normalizedPattern) ?? HostPatterns::throwInvalid(
                pattern: $pattern,
            );
            if ($port !== null && preg_match(pattern: HostPatterns::PORT_PATTERN, subject: $port) !== 1) {
                HostPatterns::throwInvalid(pattern: $pattern);
            }
            if ($port !== null && (int) $port > 65535) {
                HostPatterns::throwInvalid(pattern: $pattern);
            }
            if (str_starts_with(haystack: $name, needle: '*.')) {
                $baseName = substr(string: $name, offset: 2);
                if (!HostPatterns::isWildcardBaseName(name: $baseName)) {
                    HostPatterns::throwInvalid(pattern: $pattern);
                }
                $wildcards[] = ['suffix' => '.' . $baseName, 'port' => $port];

                continue;
            }
            if (!HostPatterns::isIpLiteral(name: $name) && !HostPatterns::isHostName(name: $name)) {
                HostPatterns::throwInvalid(pattern: $pattern);
            }
            $exactHosts[$normalizedPattern] = true;
        }
        $this->patterns = $normalizedPatterns;
        $this->exactHosts = $exactHosts;
        $this->wildcards = $wildcards;
    }

    #[Override]
    public function isAllowed(string $host): bool
    {
        $host = strtolower(string: $host);
        if (array_key_exists(key: $host, array: $this->exactHosts)) {
            return true;
        }
        if ($this->wildcards === [] || preg_match(pattern: HostPatterns::HOST_CHARACTERS_PATTERN, subject: $host) !== 1) {
            return false;
        }
        $parts = HostPatterns::splitPort(host: $host);
        if ($parts === null) {
            return false;
        }
        [$name, $port] = $parts;
        foreach ($this->wildcards as $wildcard) {
            if ($wildcard['port'] !== $port || !str_ends_with(haystack: $name, needle: $wildcard['suffix'])) {
                continue;
            }
            // The rest must be exactly one label: no dot, no empty label
            $label = substr(string: $name, offset: 0, length: -strlen(string: $wildcard['suffix']));
            if (
                preg_match(pattern: HostPatterns::LABEL_PATTERN, subject: $label) === 1
                && strlen(string: $name) <= HostPatterns::MAX_NAME_LENGTH
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string> The patterns as given, in lower case
     */
    public function getPatterns(): array
    {
        return $this->patterns;
    }

    /**
     * Splits `name:port` and `[ipv6]:port`; null if a colon is not a port separator.
     *
     * @return ?array{string, ?string}
     */
    private static function splitPort(string $host): ?array
    {
        if (str_starts_with(haystack: $host, needle: '[')) {
            $end = strpos(haystack: $host, needle: ']');
            if ($end === false) {
                return null;
            }
            $rest = substr(string: $host, offset: $end + 1);
            if ($rest === '') {
                return [$host, null];
            }

            return str_starts_with(haystack: $rest, needle: ':')
                ? [substr(string: $host, offset: 0, length: $end + 1), substr(string: $rest, offset: 1)]
                : null;
        }
        $parts = explode(separator: ':', string: $host);

        return match (count(value: $parts)) {
            1 => [$host, null],
            2 => [$parts[0], $parts[1]],
            default => null,
        };
    }

    private static function isHostName(string $name): bool
    {
        if (strlen(string: $name) > HostPatterns::MAX_NAME_LENGTH) {
            return false;
        }
        foreach (explode(separator: '.', string: $name) as $label) {
            if (preg_match(pattern: HostPatterns::LABEL_PATTERN, subject: $label) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * At least two labels (no `*.com`) and a top-level domain that is not a number (no `*.0.1` of an IP address).
     */
    private static function isWildcardBaseName(string $name): bool
    {
        $labels = explode(separator: '.', string: $name);

        return count(value: $labels) >= 2
            && HostPatterns::isHostName(name: $name)
            && preg_match(pattern: '/^[0-9]+$/D', subject: $labels[count(value: $labels) - 1]) !== 1;
    }

    private static function isIpLiteral(string $name): bool
    {
        if (str_starts_with(haystack: $name, needle: '[') && str_ends_with(haystack: $name, needle: ']')) {
            return filter_var(
                value: substr(string: $name, offset: 1, length: -1),
                filter: FILTER_VALIDATE_IP,
                options: FILTER_FLAG_IPV6,
            ) !== false;
        }

        return filter_var(value: $name, filter: FILTER_VALIDATE_IP, options: FILTER_FLAG_IPV4) !== false;
    }

    private static function throwInvalid(string $pattern): never
    {
        throw new InvalidArgumentException(
            message: 'The host pattern "' . $pattern . '" is invalid: use "example.ch", "*.example.ch", '
            . '"example.ch:8443", "127.0.0.1" or "[::1]".',
        );
    }
}
