<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use actra\yuf\table\TableItemModel;

class BooleanColumn extends AbstractTableColumn
{
    public string $trueLabel = 'Ja';
    public string $falseLabel = 'Nein';

    protected function renderCellValue(TableItemModel $tableItemModel): string
    {
        $value = $tableItemModel->getRawValue(name: $this->identifier);

        if (is_null(value: $value)) {
            return '';
        }

        if ($value === 1 || $value === true) {
            return $this->trueLabel;
        }

        if ($value === 0 || $value === false) {
            return $this->falseLabel;
        }

        return $tableItemModel->renderValue(name: $this->identifier);
    }
}