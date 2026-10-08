<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

/**
 * Checks if the domain of an email address can receive mail (network access, so it is an interface for the tests).
 * Extension point: a project can replace the check, `SystemMailDomainResolver` is the default.
 */
interface MailDomainResolver
{
    /**
     * @param string $domain The domain of a valid address (ASCII, punycode for international domain names)
     *
     * @return ?EmailAddressError `null` if the domain can receive mail
     */
    public function findProblem(string $domain): ?EmailAddressError;
}
