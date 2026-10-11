<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\common;

use actra\yuf\common\EmailAddressError;
use actra\yuf\common\MailServerLookup;
use Override;

/**
 * Answers the lookups with fixed records and remembers the addresses it was asked to connect to.
 */
final class StubMailServerLookup implements MailServerLookup
{
    /** @var list<string> */
    public private(set) array $connectedAddresses = [];

    /**
     * @param list<string> $mxHosts
     * @param list<string>|EmailAddressError $ipv4Addresses
     */
    public function __construct(
        private readonly array $mxHosts = [],
        private readonly array|EmailAddressError $ipv4Addresses = [],
        private readonly ?string $smtpConnectionProblem = null,
    ) {}

    #[Override]
    public function findMxHosts(string $domain): array
    {
        return $this->mxHosts;
    }

    #[Override]
    public function findIpv4Addresses(string $domain): array|EmailAddressError
    {
        return $this->ipv4Addresses;
    }

    #[Override]
    public function findSmtpConnectionProblem(string $ipAddress): ?string
    {
        $this->connectedAddresses[] = $ipAddress;

        return $this->smtpConnectionProblem;
    }
}
