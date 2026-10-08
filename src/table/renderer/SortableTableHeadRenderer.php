<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\renderer;

use actra\yuf\pagination\LinkQuery;
use actra\yuf\table\column\AbstractTableColumn;
use actra\yuf\table\table\DbResultTable;
use actra\yuf\table\table\SmartTable;
use actra\yuf\table\TableSortDirectionEnum;
use LogicException;
use Override;

/**
 * Head of a `DbResultTable`: the labels of sortable columns are links that sort by the column. The classes and label
 * additions are HTML of the application.
 */
final class SortableTableHeadRenderer extends TableHeadRenderer
{
    public string $sortableColumnClass = 'sort';
    public string $sortableColumnClassActiveAsc = 'sort sort-asc';
    public string $sortableColumnClassActiveDesc = 'sort sort-desc';
    public string $sortLinkClassActiveAsc = '';
    public string $sortLinkClassActiveDesc = '';
    public string $sortableColumnLabelAddition = '';
    public string $sortableColumnLabelAdditionActiveAsc = '';
    public string $sortableColumnLabelAdditionActiveDesc = '';

    #[Override]
    public function render(SmartTable $smartTable): string
    {
        if (!($smartTable instanceof DbResultTable)) {
            throw new LogicException(
                message: 'The table "' . $smartTable->identifier . '" is a ' . $smartTable::class
                . ', but SortableTableHeadRenderer needs a DbResultTable (the sorting is a query of the database).',
            );
        }

        return parent::render(smartTable: $smartTable);
    }

    #[Override]
    protected function renderColumnHead(AbstractTableColumn $abstractTableColumn, SmartTable $smartTable): string
    {
        $columnCssClasses = $abstractTableColumn->columnCssClasses;
        if (!$abstractTableColumn->isSortable || !($smartTable instanceof DbResultTable)) {
            return $this->renderHeadCell(columnCssClasses: $columnCssClasses, contentHtml: $abstractTableColumn->label);
        }

        $isActiveSortColumn = $smartTable->getCurrentSortColumn() === $abstractTableColumn->identifier;
        $sortDirection = $isActiveSortColumn
            ? $smartTable->getCurrentSortDirection()->opposite()
            : TableSortDirectionEnum::fromAscending(ascending: $abstractTableColumn->sortAscendingByDefault);
        $sortLinkAttributes = [
            'a',
            'href="' . LinkQuery::create(
                name: 'sort',
                value: implode(separator: '|', array: [
                    urlencode(string: $smartTable->identifier),
                    urlencode(string: $abstractTableColumn->identifier),
                    $sortDirection->value,
                ]),
                additionalParameters: $smartTable->additionalLinkParameters,
            ) . '"',
        ];
        if ($isActiveSortColumn) {
            // The links says where the next click leads to, the classes say how the column is sorted now
            $isSortedAscending = !$sortDirection->isAscending();
            $sortLinkClass = $isSortedAscending ? $this->sortLinkClassActiveAsc : $this->sortLinkClassActiveDesc;
            if ($sortLinkClass !== '') {
                $sortLinkAttributes[] = 'class="' . $sortLinkClass . '"';
            }
            $columnCssClasses[] = $isSortedAscending
                ? $this->sortableColumnClassActiveAsc
                : $this->sortableColumnClassActiveDesc;
            $labelAddition = $isSortedAscending
                ? $this->sortableColumnLabelAdditionActiveAsc
                : $this->sortableColumnLabelAdditionActiveDesc;
        } else {
            $columnCssClasses[] = $this->sortableColumnClass;
            $labelAddition = $this->sortableColumnLabelAddition;
        }

        return $this->renderHeadCell(
            columnCssClasses: $columnCssClasses,
            contentHtml: '<' . implode(separator: ' ', array: $sortLinkAttributes) . '>'
            . $abstractTableColumn->label . $labelAddition . '</a>',
        );
    }
}
