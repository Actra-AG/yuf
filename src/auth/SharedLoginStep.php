<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

/**
 * A step of a shared login on a hand-over page (`SharedLogin::receiveOnSite()`, `receiveOnLoginHost()`): log the user
 * in (if any), then redirect the browser (`HttpResponse::redirectAndExit()` with `sameSiteLaxSessionHandler:`).
 */
final readonly class SharedLoginStep
{
    /**
     * @param string $redirectUrl Where to send the browser: a local path of this host or an absolute URL of the other
     *     host of the step (never taken from the request)
     * @param ?SharedLoginUser $logIn The user to log in on this host before the redirect, `null` for none
     * @param ?string $failure Why the step was refused (for the log; the redirect goes to the login page), `null` if
     *     it was not refused
     */
    public function __construct(
        public string $redirectUrl,
        public ?SharedLoginUser $logIn = null,
        public ?string $failure = null,
    ) {}
}
