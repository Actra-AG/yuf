<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\db;

use actra\yuf\clock\FixedClock;
use actra\yuf\db\DbQueryLogList;
use actra\yuf\tests\Double\db\SteppingClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DbQueryLogListTest extends TestCase
{
    public function testNewListIsEmpty(): void
    {
        $this->assertSame([], new DbQueryLogList(clock: new FixedClock(now: new DateTimeImmutable()))->getItems());
    }

    public function testStartedItemIsNotPartOfTheListUntilItIsAdded(): void
    {
        $queryLog = new DbQueryLogList(clock: new FixedClock(now: new DateTimeImmutable()));

        $item = $queryLog->start(sqlQuery: 'SELECT ?', params: [1]);

        $this->assertSame([], $queryLog->getItems());
        $this->assertSame('SELECT ?', $item->sqlQuery);
        $this->assertSame([1], $item->params);
    }

    public function testItemsAreKeptInTheOrderTheyWereAdded(): void
    {
        $queryLog = new DbQueryLogList(clock: new FixedClock(now: new DateTimeImmutable()));
        $first = $queryLog->start(sqlQuery: 'SELECT 1', params: []);
        $second = $queryLog->start(sqlQuery: 'SELECT 2', params: []);

        $queryLog->add(dbQueryLogItem: $second);
        $queryLog->add(dbQueryLogItem: $first);

        $this->assertSame([$second, $first], $queryLog->getItems());
    }

    public function testListsAreIndependent(): void
    {
        $first = new DbQueryLogList(clock: new FixedClock(now: new DateTimeImmutable()));
        $second = new DbQueryLogList(clock: new FixedClock(now: new DateTimeImmutable()));

        $first->add(dbQueryLogItem: $first->start(sqlQuery: 'SELECT 1', params: []));

        $this->assertCount(1, $first->getItems());
        $this->assertSame([], $second->getItems());
    }

    public function testItemsMeasureWithTheClockOfTheList(): void
    {
        $queryLog = new DbQueryLogList(clock: new SteppingClock());

        $item = $queryLog->start(sqlQuery: 'SELECT 1', params: []);
        $item->confirmFinishedExecution();

        $this->assertEqualsWithDelta(1.0, $item->getExecutionTime(), 0.000001);
    }
}
