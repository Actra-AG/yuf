<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

/**
 * The keys of the login state in the session (`yuf.auth`).
 */
enum AuthSessionKeyEnum: string
{
    case IS_LOGGED_IN = 'isLoggedIn';
    case AUTH_SESSION_ID = 'authSessionId';
}
