<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table;

class TableItemCollection
{
    /** @var TableItemModel[] */
    private array $items = [];
    private int $amount = 0;

    public function add(TableItemModel $tableItemModel): void
    {
        $this->items[] = $tableItemModel;
        $this->amount++;
    }

    /**
     * @return TableItemModel[]
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