<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use actra\yuf\table\TableItemModel;
use Override;

class DefaultColumn extends AbstractTableColumn
{
    public bool $renderNewLines = true;

    #[Override]
    protected function renderCellValue(TableItemModel $tableItemModel): string
    {
        return $tableItemModel->renderValue(name: $this->identifier, renderNewLines: $this->renderNewLines);
    }
}
