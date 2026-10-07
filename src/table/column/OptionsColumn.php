<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use actra\yuf\table\TableItem;
use Override;

class OptionsColumn extends AbstractTableColumn
{
    public function __construct(
        string $identifier,
        string $label,
        private readonly array $options,
        bool $isOrderAble,
        bool $orderAscending = true,
    ) {
        parent::__construct(
            identifier: $identifier,
            label: $label,
            isSortable: $isOrderAble,
            sortAscendingByDefault: $orderAscending,
        );
    }

    #[Override]
    protected function renderCellValue(TableItem $tableItem): string
    {
        $rawValue = $tableItem->getRawValue($this->identifier);
        if (array_key_exists(key: $rawValue, array: $this->options)) {
            return $this->options[$rawValue];
        }

        return $tableItem->renderValue(name: $this->identifier);
    }
}
