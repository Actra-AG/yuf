<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\db;

use actra\yuf\clock\Clock;
use actra\yuf\db\DbQueryLogItem;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DbQueryLogItemTest extends TestCase
{
    public function testExecutionTimeIsTheDifferenceOfTheClockTimes(): void
    {
        $clock = new class implements Clock {
            private int $calls = 0;

            public function now(): DateTimeImmutable
            {
                $this->calls++;

                return new DateTimeImmutable(datetime: $this->calls === 1 ? '@1800000000.25' : '@1800000002');
            }
        };

        $item = new DbQueryLogItem(sqlQuery: 'SELECT 1', params: [], clock: $clock);
        $item->confirmFinishedExecution();

        $this->assertEqualsWithDelta(1.75, $item->getExecutionTime(), 0.000001);
    }
}
