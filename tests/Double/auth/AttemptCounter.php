<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\auth;

/**
 * The counter of wrong password attempts in the database row of a user, shared by the `TestAuthUser` objects of
 * parallel requests.
 */
final class AttemptCounter
{
    public function __construct(public int $value = 0) {}
}
