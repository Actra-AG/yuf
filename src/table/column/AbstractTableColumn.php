<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use actra\yuf\common\StringUtils;
use actra\yuf\table\TableItem;

/**
 * Extension point: a column type of a project extends this class and renders the content of one cell (HTML, so it must
 * encode values: `TableItem::renderValue()` does).
 *
 * `$label` is HTML as it is output in the table head (a text of the application, never user input).
 */
abstract class AbstractTableColumn
{
    /** @var list<string> */
    public private(set) array $columnCssClasses = [];
    /** @var list<string> */
    public private(set) array $cellCssClasses = [];
    /** Set by the table the column is added to. */
    public ?string $tableIdentifier = null;

    public function __construct(
        public readonly string $identifier,
        public readonly string $label,
        public readonly bool $isSortable = false,
        public readonly bool $sortAscendingByDefault = true,
        public readonly string $sortableColumnClass = 'sort',
    ) {
        if ($this->isSortable) {
            $this->addColumnCssClass(className: $sortableColumnClass);
        }
    }

    public function addColumnCssClass(string $className): void
    {
        if (in_array(
            needle: $className,
            haystack: $this->columnCssClasses,
            strict: true,
        )) {
            return;
        }
        $this->columnCssClasses[] = $className;
    }

    public function addCellCssClass(string $className): void
    {
        if (in_array(
            needle: $className,
            haystack: $this->cellCssClasses,
            strict: true,
        )) {
            return;
        }
        $this->cellCssClasses[] = $className;
    }

    public function renderCell(TableItem $tableItem): string
    {
        $attributesArr = ['td'];
        if ($this->cellCssClasses !== []) {
            $attributesArr[] = 'class="' . implode(separator: ' ', array: $this->cellCssClasses) . '"';
        }

        return implode(
            separator: StringUtils::IMPLODE_DEFAULT_SEPARATOR,
            array: [
                '<' . implode(separator: ' ', array: $attributesArr) . '>',
                $this->renderCellValue(tableItem: $tableItem),
                '</td>',
            ],
        );
    }

    /**
     * @return string HTML, output as it is
     */
    abstract protected function renderCellValue(TableItem $tableItem): string;
}
