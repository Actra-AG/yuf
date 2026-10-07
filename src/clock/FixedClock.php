<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\clock;

use DateTimeImmutable;
use Override;

/**
 * Always returns the same time. Use it in tests of yuf and of projects that build upon yuf.
 */
final readonly class FixedClock implements Clock
{
    public function __construct(private DateTimeImmutable $now) {}

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
