<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

use Override;

/**
 * Looks up the mail servers with the DNS functions of PHP and connects to port 25.
 */
final readonly class SystemMailServerLookup implements MailServerLookup
{
    private const int SMTP_PORT = 25;
    /** Short: the check runs while the user waits for the form */
    private const int CONNECTION_TIMEOUT_IN_SECONDS = 2;

    #[Override]
    public function findMxHosts(string $domain): array
    {
        $mxHosts = [];
        if (!getmxrr(hostname: $domain, hosts: $mxHosts)) {
            return [];
        }

        return $mxHosts;
    }

    #[Override]
    public function findIpv4Addresses(string $domain): array|EmailAddressError
    {
        $errorMessage = null;
        $aRecords = SystemMailServerLookup::runCollectingWarning(
            function: static fn(): array|false => dns_get_record(hostname: $domain, type: DNS_A),
            warning: $errorMessage,
        );
        if ($aRecords === false) {
            return new EmailAddressError(
                code: EmailAddressErrorEnum::DNS_GET_RECORD,
                message: $errorMessage ?? 'The DNS lookup failed.',
            );
        }
        $ipAddresses = [];
        foreach ($aRecords as $aRecord) {
            if (array_key_exists(key: 'ip', array: $aRecord) && is_string(value: $aRecord['ip'])) {
                $ipAddresses[] = $aRecord['ip'];
            }
        }

        return $ipAddresses;
    }

    #[Override]
    public function findSmtpConnectionProblem(string $ipAddress): ?string
    {
        $errorMessage = null;
        $connection = SystemMailServerLookup::runCollectingWarning(
            function: static fn() => fsockopen(
                hostname: $ipAddress,
                port: SystemMailServerLookup::SMTP_PORT,
                timeout: SystemMailServerLookup::CONNECTION_TIMEOUT_IN_SECONDS,
            ),
            warning: $errorMessage,
        );
        if ($connection === false) {
            return 'Failed to connect to port 25' . ($errorMessage === null ? '' : ': ' . $errorMessage);
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
