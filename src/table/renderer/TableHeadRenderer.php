<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\renderer;

use actra\yuf\table\column\AbstractTableColumn;
use actra\yuf\table\table\SmartTable;

/**
 * Extension point: a project overrides `renderColumnHead()` to change the head cell of a column.
 */
class TableHeadRenderer
{
    protected bool $addColumnScopeAttribute = true;

    public function render(SmartTable $smartTable): string
    {
        $columns = [];

        foreach ($smartTable->columns as $abstractTableColumn) {
            $columns[] = $this->renderColumnHead(
                abstractTableColumn: $abstractTableColumn,
                smartTable: $smartTable,
            );
        }

        return implode(separator: PHP_EOL, array: [
            '<tr>',
            implode(separator: PHP_EOL, array: $columns),
            '</tr>',
        ]);
    }

    protected function renderColumnHead(AbstractTableColumn $abstractTableColumn, SmartTable $smartTable): string
    {
        return $this->renderHeadCell(
            columnCssClasses: $abstractTableColumn->columnCssClasses,
            contentHtml: $abstractTableColumn->label,
        );
    }

    /**
     * @param list<string> $columnCssClasses
     * @param string $contentHtml HTML, output as it is
     */
    protected function renderHeadCell(array $columnCssClasses, string $contentHtml): string
    {
        $attributesArr = ['th'];
        if ($this->addColumnScopeAttribute) {
            $attributesArr[] = 'scope="col"';
        }
        if ($columnCssClasses !== []) {
            $attributesArr[] = 'class="' . implode(separator: ' ', array: $columnCssClasses) . '"';
        }

        return '<' . implode(separator: ' ', array: $attributesArr) . '>' . $contentHtml . '</th>';
    }
}
