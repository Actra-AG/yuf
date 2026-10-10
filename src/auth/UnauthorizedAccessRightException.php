<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\exception\UnauthorizedException;

/**
 * A view requires access rights that the user does not have.
 *
 * `$isNotLoggedIn` tells nobody is logged in (the view got no user), as opposed to a user without the right: with a
 * login path on the route collection (`RouteCollection::$loginPath`) the first is answered with a redirect to the
 * login, the second with the error page.
 */
final class UnauthorizedAccessRightException extends UnauthorizedException
{
    public function __construct(
        string $message = 'Unauthorized',
        HttpStatusCodeEnum $code = HttpStatusCodeEnum::HTTP_UNAUTHORIZED,
        public readonly bool $isNotLoggedIn = false,
    ) {
        parent::__construct(message: $message, code: $code);
    }
}
