<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use actra\yuf\common\StringUtils;
use actra\yuf\table\TableItemModel;

class FileSizeColumn extends AbstractTableColumn
{
    public int $decimals = 2;

    protected function renderCellValue(TableItemModel $tableItemModel): string
    {
        $bytes = $tableItemModel->getRawValue(name: $this->identifier);
        if (is_null(value: $bytes)) {
            return '';
        }

        return StringUtils::formatBytes(bytes: $bytes, precision: $this->decimals);
    }
}