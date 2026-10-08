<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\common;

use actra\yuf\common\EmailAddressError;
use actra\yuf\common\MailDomainResolver;
use Override;

/**
 * Answers every domain with the same problem (or without one) and remembers the checked domains.
 */
final class FixedMailDomainResolver implements MailDomainResolver
{
    /** @var list<string> */
    public private(set) array $checkedDomains = [];

    public function __construct(private readonly ?EmailAddressError $problem = null) {}

    #[Override]
    public function findProblem(string $domain): ?EmailAddressError
    {
        $this->checkedDomains[] = $domain;

        return $this->problem;
    }
}
