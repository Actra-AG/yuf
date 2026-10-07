<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table;

class TableItemCollection
{
    /** @var TableItem[] */
    private array $items = [];
    private int $amount = 0;

    public function add(TableItem $tableItem): void
    {
        $this->items[] = $tableItem;
        $this->amount++;
    }

    /**
     * @return TableItem[]
     */
    public function list(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return $this->amount;
    }
}
