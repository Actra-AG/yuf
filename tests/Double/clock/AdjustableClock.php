<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\clock;

use actra\yuf\clock\Clock;
use DateTimeImmutable;
use Override;

/**
 * A clock that stands still until the test moves it.
 */
final class AdjustableClock implements Clock
{
    public function __construct(private DateTimeImmutable $now) {}

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advanceSeconds(int $seconds): void
    {
        $this->now = $this->now->modify(modifier: '+' . $seconds . ' seconds');
    }
}
