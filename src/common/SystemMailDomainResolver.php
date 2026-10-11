<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

use Override;

/**
 * Looks up the MX records of the domain. A domain with a null MX (RFC 7505: the MX host ".") accepts no mail. Without
 * an MX record, the domain must have an A record of a public address that accepts connections on port 25. Addresses
 * of private and reserved ranges are not contacted, so an entered domain cannot make the server connect to its
 * internal network.
 */
final readonly class SystemMailDomainResolver implements MailDomainResolver
{
    public function __construct(private MailServerLookup $mailServerLookup = new SystemMailServerLookup()) {}

    #[Override]
    public function findProblem(string $domain): ?EmailAddressError
    {
        $mxHosts = $this->mailServerLookup->findMxHosts(domain: $domain);
        if ($mxHosts !== []) {
            // A null MX next to other MX records is invalid (RFC 7505, section 3): rejected as well
            if (in_array(needle: '', haystack: $mxHosts, strict: true)
                || in_array(needle: '.', haystack: $mxHosts, strict: true)) {
                return new EmailAddressError(
                    code: EmailAddressErrorEnum::NULL_MX,
                    message: 'The domain accepts no mail (null MX record)',
                );
            }

            // The note of https://www.php.net/manual/en/function.getmxrr is ignored on purpose: without MX records,
            // the host itself is the mail exchanger (RFC 2821), which the port 25 check below tests.
            return null;
        }
        $ipAddresses = $this->mailServerLookup->findIpv4Addresses(domain: $domain);
        if ($ipAddresses instanceof EmailAddressError) {
            return $ipAddresses;
        }
        if ($ipAddresses === []) {
            return new EmailAddressError(
                code: EmailAddressErrorEnum::NO_DNS_RECORDS,
                message: 'No A-Records found for the domain',
            );
        }

        return $this->checkSmtpPort(ipAddress: $ipAddresses[0]);
    }

    private function checkSmtpPort(string $ipAddress): ?EmailAddressError
    {
        $isPublicAddress = filter_var(
            value: $ipAddress,
            filter: FILTER_VALIDATE_IP,
            options: FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
        if ($isPublicAddress === false) {
            return new EmailAddressError(
                code: EmailAddressErrorEnum::NOT_RESOLVABLE,
                message: 'The A-Record is no public address',
            );
        }
        $connectionProblem = $this->mailServerLookup->findSmtpConnectionProblem(ipAddress: $ipAddress);
        if ($connectionProblem !== null) {
            return new EmailAddressError(code: EmailAddressErrorEnum::NOT_RESOLVABLE, message: $connectionProblem);
        }

        return null;
    }
}
