<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table;

use actra\yuf\db\DbQuery;
use actra\yuf\db\FrameworkDB;
use actra\yuf\table\column\ActionsColumn;
use actra\yuf\table\column\CallbackColumn;
use actra\yuf\table\column\DateColumn;
use actra\yuf\table\column\DefaultColumn;
use actra\yuf\table\column\OptionsColumn;
use actra\yuf\table\filter\TableFilter;
use actra\yuf\table\renderer\SortableTableHeadRenderer;
use actra\yuf\table\renderer\TableHeadRenderer;
use actra\yuf\table\renderer\TablePaginationRenderer;
use actra\yuf\table\table\DbResultTable;
use actra\yuf\table\table\SmartTable;

class TableHelper
{
    public const string SORT_ASC = 'ASC';
    public const string SORT_DESC = 'DESC';
    public const array OPPOSITE_SORT_DIRECTION = [
        TableHelper::SORT_ASC => TableHelper::SORT_DESC,
        TableHelper::SORT_DESC => TableHelper::SORT_ASC,
    ];

    public static function createTable(string $identifier, ?TableHeadRenderer $tableHeadRenderer = null): SmartTable
    {
        return new SmartTable(
            identifier: $identifier,
            tableHeadRenderer: $tableHeadRenderer,
            tableItemCollection: new TableItemCollection()
        );
    }

    public static function createDbResultTable(
        string $identifier,
        FrameworkDB $db,
        string $selectQuery,
        array $params = [],
        ?TableFilter $tableFilter = null,
        ?TablePaginationRenderer $tablePaginationRenderer = null,
        ?SortableTableHeadRenderer $sortableTableHeadRenderer = null,
        int $itemsPerPage = 25
    ): DbResultTable {
        return new DbResultTable(
            identifier: $identifier,
            db: $db,
            dbQuery: DbQuery::createFromSqlQuery(query: $selectQuery, parameters: $params),
            tableFilter: $tableFilter,
            tablePaginationRenderer: $tablePaginationRenderer,
            sortableTableHeadRenderer: $sortableTableHeadRenderer,
            itemsPerPage: $itemsPerPage
        );
    }

    public static function createActionsColumn(
        string $identifier,
        string $label = '',
        string $cellCssClass = 'action'
    ): ActionsColumn {
        return new ActionsColumn(
            identifier: $identifier,
            label: $label,
            cellCssClass: $cellCssClass
        );
    }

    public static function createDateColumn(
        string $identifier,
        string $label,
        bool $isSortable = false,
        bool $sortAscendingByDefault = true
    ): DateColumn {
        return new DateColumn(
            identifier: $identifier,
            label: $label,
            isSortable: $isSortable,
            sortAscendingByDefault: $sortAscendingByDefault
        );
    }

    public static function createDefaultColumn(
        string $identifier,
        string $label,
        bool $isSortable = false,
        bool $sortAscendingByDefault = true
    ): DefaultColumn {
        return new DefaultColumn(
            identifier: $identifier,
            label: $label,
            isSortable: $isSortable,
            sortAscendingByDefault: $sortAscendingByDefault
        );
    }

    public static function createOptionsColumn(
        string $identifier,
        string $label,
        array $options,
        bool $isOrderAble,
        bool $orderAscending = true
    ): OptionsColumn {
        return new OptionsColumn(
            identifier: $identifier,
            label: $label,
            options: $options,
            isOrderAble: $isOrderAble,
            orderAscending: $orderAscending
        );
    }

    public static function createCallbackColumn(
        string $identifier,
        string $label,
        callable $callbackFunction,
        bool $isSortable = false,
        bool $sortAscendingByDefault = true
    ): CallbackColumn {
        return new CallbackColumn(
            identifier: $identifier,
            label: $label,
            callbackFunction: $callbackFunction,
            isSortable: $isSortable,
            sortAscendingByDefault: $sortAscendingByDefault
        );
    }
}