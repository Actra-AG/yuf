<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

use Override;

/**
 * Accepts every domain without a lookup: for development (`debug`), where all mail goes to a local mail catcher and
 * test domains (`.test`, `.invalid`, `example.com`) have no mail server. `Core` uses it for the `FormContext` in debug
 * mode.
 */
final readonly class AcceptingMailDomainResolver implements MailDomainResolver
{
    #[Override]
    public function findProblem(string $domain): ?EmailAddressError
    {
        return null;
    }
}
