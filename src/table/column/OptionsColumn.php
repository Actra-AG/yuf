<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use actra\yuf\table\TableItemModel;

class OptionsColumn extends AbstractTableColumn
{
    public function __construct(
        string $identifier,
        string $label,
        private readonly array $options,
        bool $isOrderAble,
        bool $orderAscending = true
    ) {
        parent::__construct(
            identifier: $identifier,
            label: $label,
            isSortable: $isOrderAble,
            sortAscendingByDefault: $orderAscending
        );
    }

    protected function renderCellValue(TableItemModel $tableItemModel): string
    {
        $rawValue = $tableItemModel->getRawValue($this->identifier);
        if (array_key_exists(key: $rawValue, array: $this->options)) {
            return $this->options[$rawValue];
        }

        return $tableItemModel->renderValue(name: $this->identifier);
    }
}