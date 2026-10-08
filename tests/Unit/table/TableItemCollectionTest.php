<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\table;

use actra\yuf\table\TableItem;
use actra\yuf\table\TableItemCollection;
use PHPUnit\Framework\TestCase;

final class TableItemCollectionTest extends TestCase
{
    public function testItemsKeepTheirOrderAndAreCounted(): void
    {
        $collection = new TableItemCollection();
        $first = new TableItem(dataObject: (object) ['a' => 1]);
        $second = new TableItem(dataObject: (object) ['a' => 2]);

        $this->assertSame(0, $collection->count());
        $this->assertSame([], $collection->list());

        $collection->add(tableItem: $first);
        $collection->add(tableItem: $second);

        $this->assertSame(2, $collection->count());
        $this->assertSame([$first, $second], $collection->list());
    }
}
