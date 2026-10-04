<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\security;

/**
 * Where the CSRF token of the current user comes from and how a posted token is checked against it.
 * `SessionCsrfTokenSource` is the production implementation.
 */
interface CsrfTokenSource
{
    /**
     * The token the form has to send back (created on first use).
     */
    public function getToken(): string;

    /**
     * Whether the given (posted) token is the token of the current user.
     */
    public function isValid(string $token): bool;
}