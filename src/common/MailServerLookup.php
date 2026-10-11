<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

/**
 * The network access of `SystemMailDomainResolver`: DNS lookups and the connection to port 25 (an interface for the
 * tests).
 */
interface MailServerLookup
{
    /**
     * @return list<string> The MX hosts of the domain (`''` or `'.'` for a null MX, RFC 7505); empty if the domain has
     *                      no MX record or the lookup failed
     */
    public function findMxHosts(string $domain): array;

    /**
     * @return list<string>|EmailAddressError The IPv4 addresses of the A records, or the error `DNS_GET_RECORD` if
     *                                        the lookup failed
     */
    public function findIpv4Addresses(string $domain): array|EmailAddressError;

    /**
     * @return ?string `null` if the address accepts connections on port 25, otherwise the reason
     */
    public function findSmtpConnectionProblem(string $ipAddress): ?string;
}
