<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use actra\yuf\table\TableItem;
use Override;

/**
 * The value of the column as encoded text.
 */
final class DefaultColumn extends AbstractTableColumn
{
    public bool $renderNewLines = true;

    #[Override]
    protected function renderCellValue(TableItem $tableItem): string
    {
        return $tableItem->renderValue(name: $this->identifier, renderNewLines: $this->renderNewLines);
    }
}
