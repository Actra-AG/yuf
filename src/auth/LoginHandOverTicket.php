<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

/**
 * A redeemed login ticket (`LoginHandOver::redeemTicket()`): the user to log in and the page to return to.
 */
final readonly class LoginHandOverTicket
{
    /**
     * @param string $subject The ID of the user, the same on the login host and on the site
     * @param string $returnPath The local path the site redirects to after the login (stored by the site itself)
     */
    public function __construct(
        public string $subject,
        public string $returnPath,
    ) {}
}
