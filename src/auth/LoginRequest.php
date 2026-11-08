<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

/**
 * A valid login request of a site on the login host (`LoginHandOver::findLoginRequest()`). `createHandOverUrl()` reads
 * the stored request again by its ID, so nothing of this object is trusted.
 */
final readonly class LoginRequest
{
    /**
     * @param string $id The ID to carry through the steps of the login (`LoginHandOver::REQUEST_PARAMETER`)
     * @param string $host The host of the site the visitor returns to (e.g. for "Log in to site-a.example.com")
     * @param int $expiresAt Unix time when the request expires
     */
    public function __construct(
        public string $id,
        public string $host,
        public int $expiresAt,
    ) {}
}
