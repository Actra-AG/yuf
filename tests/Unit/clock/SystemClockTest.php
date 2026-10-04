<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\clock;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use PHPUnit\Framework\TestCase;

final class SystemClockTest extends TestCase
{
    public function testIsAClock(): void
    {
        $this->assertInstanceOf(Clock::class, new SystemClock());
    }

    public function testReturnsTheCurrentTime(): void
    {
        $before = time();
        $now = new SystemClock()->now()->getTimestamp();
        $after = time();

        $this->assertGreaterThanOrEqual($before, $now);
        $this->assertLessThanOrEqual($after, $now);
    }

    public function testTimeAdvancesBetweenCalls(): void
    {
        $clock = new SystemClock();
        $first = $clock->now();
        usleep(microseconds: 2000);

        $this->assertGreaterThan($first, $clock->now());
    }
}