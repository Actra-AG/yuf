<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use actra\yuf\table\TableItem;
use Override;

class BooleanColumn extends AbstractTableColumn
{
    public string $trueLabel = 'Ja';
    public string $falseLabel = 'Nein';

    #[Override]
    protected function renderCellValue(TableItem $tableItem): string
    {
        $value = $tableItem->getRawValue(name: $this->identifier);

        if ($value === null) {
            return '';
        }

        if ($value === 1 || $value === true) {
            return $this->trueLabel;
        }

        if ($value === 0 || $value === false) {
            return $this->falseLabel;
        }

        return $tableItem->renderValue(name: $this->identifier);
    }
}
