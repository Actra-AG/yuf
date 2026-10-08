<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\security;

use actra\yuf\security\CsrfTokenSource;
use Override;

/**
 * Counts how often the token is asked for, to check that it is only read when it is used (reading it starts the
 * session).
 */
final class CountingCsrfTokenSource implements CsrfTokenSource
{
    public int $tokenReads = 0;

    #[Override]
    public function getToken(): string
    {
        $this->tokenReads++;

        return 'counted-token';
    }

    #[Override]
    public function isValid(string $token): bool
    {
        return $token === 'counted-token';
    }
}
