<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

use Override;

/**
 * Looks up the MX records of the domain. Without an MX record, the domain must have an A record of a public address
 * that accepts connections on port 25. Addresses of private and reserved ranges are not contacted, so an entered
 * domain cannot make the server connect to its internal network.
 */
final readonly class SystemMailDomainResolver implements MailDomainResolver
{
    private const int SMTP_PORT = 25;
    /** Short: the check runs while the user waits for the form */
    private const int CONNECTION_TIMEOUT_IN_SECONDS = 2;

    #[Override]
    public function findProblem(string $domain): ?EmailAddressError
    {
        $mxRecords = [];
        if (getmxrr(hostname: $domain, hosts: $mxRecords)) {
            // The note of https://www.php.net/manual/en/function.getmxrr is ignored on purpose: without MX records,
            // the host itself is the mail exchanger (RFC 2821), which the port 25 check below tests.
            return null;
        }
        $errorMessage = null;
        $aRecords = SystemMailDomainResolver::runCollectingWarning(
            function: static fn(): array|false => dns_get_record(hostname: $domain, type: DNS_A),
            warning: $errorMessage,
        );
        if ($aRecords === false) {
            return new EmailAddressError(
                code: EmailAddressErrorEnum::DNS_GET_RECORD,
                message: $errorMessage ?? 'The DNS lookup failed.',
            );
        }
        $ipAddress = SystemMailDomainResolver::findIpAddress(aRecords: $aRecords);
        if ($ipAddress === null) {
            return new EmailAddressError(
                code: EmailAddressErrorEnum::NO_DNS_RECORDS,
                message: 'No A-Records found for the domain',
            );
        }

        return SystemMailDomainResolver::checkSmtpPort(ipAddress: $ipAddress);
    }

    /**
     * @param list<array<array-key, mixed>> $aRecords
     */
    private static function findIpAddress(array $aRecords): ?string
    {
        foreach ($aRecords as $aRecord) {
            if (array_key_exists(key: 'ip', array: $aRecord) && is_string(value: $aRecord['ip'])) {
                return $aRecord['ip'];
            }
        }

        return null;
    }

    private static function checkSmtpPort(string $ipAddress): ?EmailAddressError
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
        $errorMessage = null;
        $connection = SystemMailDomainResolver::runCollectingWarning(
            function: static fn() => fsockopen(
                hostname: $ipAddress,
                port: SystemMailDomainResolver::SMTP_PORT,
                timeout: SystemMailDomainResolver::CONNECTION_TIMEOUT_IN_SECONDS,
            ),
            warning: $errorMessage,
        );
        if ($connection === false) {
            return new EmailAddressError(
                code: EmailAddressErrorEnum::NOT_RESOLVABLE,
                message: 'Failed to connect to port 25' . ($errorMessage === null ? '' : ': ' . $errorMessage),
            );
        }
        fclose(stream: $connection);

        return null;
    }

    /**
     * Runs the function without raising its warning (a failed lookup is no program error) and returns the text of
     * the warning.
     *
     * @template T
     *
     * @param callable(): T $function
     * @param-out ?string $warning
     *
     * @return T
     */
    private static function runCollectingWarning(callable $function, ?string &$warning): mixed
    {
        $warning = null;
        set_error_handler(
            callback: static function (int $level, string $message) use (&$warning): bool {
                $warning = $message;

                return true;
            },
        );
        try {
            return $function();
        } finally {
            restore_error_handler();
        }
    }
}
