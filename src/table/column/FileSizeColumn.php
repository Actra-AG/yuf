<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use actra\yuf\common\StringUtils;
use actra\yuf\table\TableItem;
use Override;
use UnexpectedValueException;

/**
 * A size in bytes (a number or a numeric string, as a database delivers a `BIGINT`), shown with the unit.
 */
final class FileSizeColumn extends AbstractTableColumn
{
    public int $decimals = 2;

    #[Override]
    protected function renderCellValue(TableItem $tableItem): string
    {
        $bytes = $tableItem->getScalarValue(name: $this->identifier);
        if ($bytes === null) {
            return '';
        }
        if (is_string(value: $bytes) && is_numeric(value: $bytes)) {
            $bytes += 0;
        }
        if (!is_int(value: $bytes) && !is_float(value: $bytes)) {
            throw new UnexpectedValueException(
                message: 'Column "' . $this->identifier . '" holds a ' . get_debug_type(value: $bytes)
                . ', which is no size in bytes.',
            );
        }

        return StringUtils::formatBytes(bytes: $bytes, precision: $this->decimals);
    }
}
