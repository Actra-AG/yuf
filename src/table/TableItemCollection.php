<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table;

final class TableItemCollection
{
    /** @var list<TableItem> */
    private array $items = [];

    public function add(TableItem $tableItem): void
    {
        $this->items[] = $tableItem;
    }

    /**
     * @return list<TableItem>
     */
    public function list(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count(value: $this->items);
    }
}
