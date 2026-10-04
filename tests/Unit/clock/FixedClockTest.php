<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\clock;

use actra\yuf\clock\Clock;
use actra\yuf\clock\FixedClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class FixedClockTest extends TestCase
{
    public function testIsAClock(): void
    {
        $this->assertInstanceOf(Clock::class, new FixedClock(now: new DateTimeImmutable(datetime: '2026-01-02 03:04:05')));
    }

    public function testAlwaysReturnsTheGivenTime(): void
    {
        $time = new DateTimeImmutable(datetime: '2026-01-02 03:04:05.123456');
        $clock = new FixedClock(now: $time);
        usleep(microseconds: 2000);

        $this->assertEquals($time, $clock->now());
        $this->assertSame('2026-01-02 03:04:05.123456', $clock->now()->format(format: 'Y-m-d H:i:s.u'));
    }
}