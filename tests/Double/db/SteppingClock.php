<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\db;

use actra\yuf\clock\Clock;
use DateTimeImmutable;
use Override;

/**
 * Every call of `now()` is one second later than the call before, so every measured duration is a known number.
 */
final class SteppingClock implements Clock
{
    private int $calls = 0;

    #[Override]
    public function now(): DateTimeImmutable
    {
        $this->calls++;

        return new DateTimeImmutable(datetime: '@' . (1_800_000_000 + $this->calls));
    }
}
