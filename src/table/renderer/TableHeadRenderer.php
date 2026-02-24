<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\renderer;

use actra\yuf\table\column\AbstractTableColumn;
use actra\yuf\table\table\SmartTable;

class TableHeadRenderer
{
    protected bool $addColumnScopeAttribute = true;

    public function render(SmartTable $smartTable): string
    {
        $columns = [];

        foreach ($smartTable->columns as $abstractTableColumn) {
            $columns[] = $this->renderColumnHead($abstractTableColumn);
        }

        return implode(separator: PHP_EOL, array: [
            '<tr>',
            implode(separator: PHP_EOL, array: $columns),
            '</tr>',
        ]);
    }

    protected function renderColumnHead(AbstractTableColumn $abstractTableColumn): string
    {
        $columnCssClasses = $abstractTableColumn->columnCssClasses;
        $attributesArr = ['th'];
        if ($this->addColumnScopeAttribute) {
            $attributesArr[] = 'scope="col"';
        }
        if (count(value: $columnCssClasses) > 0) {
            $attributesArr[] = 'class="' . implode(separator: ' ', array: $columnCssClasses) . '"';
        }

        return '<' . implode(separator: ' ', array: $attributesArr) . '>' . $abstractTableColumn->label . '</th>';
    }
}