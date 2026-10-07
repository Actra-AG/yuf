<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\security;

use actra\yuf\security\CsrfTokenSource;

/**
 * A CSRF token source with a fixed token, without session.
 */
final readonly class InMemoryCsrfTokenSource implements CsrfTokenSource
{
    public function __construct(private string $token = 'expected-token') {}

    public function getToken(): string
    {
        return $this->token;
    }

    public function isValid(string $token): bool
    {
        return $token === $this->token;
    }
}
