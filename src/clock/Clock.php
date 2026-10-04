<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\clock;

use DateTimeImmutable;

/**
 * Source of the current time, so that time-dependent code can be tested with a `FixedClock`.
 *
 * The signature is identical to PSR-20 (`Psr\Clock\ClockInterface`), but yuf does not depend on `psr/clock`. Pass a
 * clock in through the constructor; there is no static accessor.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}