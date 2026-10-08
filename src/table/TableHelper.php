<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table;

use actra\yuf\core\HttpRequest;
use actra\yuf\db\DbQuery;
use actra\yuf\db\FrameworkDb;
use actra\yuf\html\HtmlText;
use actra\yuf\session\Session;
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
use actra\yuf\template\TemplateEngine;

/**
 * Short ways to create tables and columns. Static because the methods are pure factories without state.
 */
final class TableHelper
{
    public static function createTable(string $identifier, ?TableHeadRenderer $tableHeadRenderer = null): SmartTable
    {
        return new SmartTable(
            identifier: $identifier,
            tableHeadRenderer: $tableHeadRenderer ?? new TableHeadRenderer(),
            tableItemCollection: new TableItemCollection(),
        );
    }

    /**
     * @param list<float|int|string|null> $params One value per "?" placeholder of `$selectQuery`
     */
    public static function createDbResultTable(
        string $identifier,
        FrameworkDb $db,
        string $selectQuery,
        TemplateEngine $templateEngine,
        HttpRequest $httpRequest,
        Session $session,
        array $params = [],
        ?TableFilter $tableFilter = null,
        ?TablePaginationRenderer $tablePaginationRenderer = null,
        ?SortableTableHeadRenderer $sortableTableHeadRenderer = null,
        int $itemsPerPage = 25,
    ): DbResultTable {
        return new DbResultTable(
            identifier: $identifier,
            db: $db,
            dbQuery: DbQuery::createFromSqlQuery(query: $selectQuery, parameters: $params),
            templateEngine: $templateEngine,
            httpRequest: $httpRequest,
            session: $session,
            tableFilter: $tableFilter,
            tablePaginationRenderer: $tablePaginationRenderer,
            sortableTableHeadRenderer: $sortableTableHeadRenderer,
            itemsPerPage: $itemsPerPage,
        );
    }

    public static function createActionsColumn(
        string $identifier,
        string $label = '',
        string $cellCssClass = 'action',
    ): ActionsColumn {
        return new ActionsColumn(
            identifier: $identifier,
            label: $label,
            cellCssClass: $cellCssClass,
        );
    }

    public static function createDateColumn(
        string $identifier,
        string $label,
        bool $isSortable = false,
        bool $sortAscendingByDefault = true,
    ): DateColumn {
        return new DateColumn(
            identifier: $identifier,
            label: $label,
            isSortable: $isSortable,
            sortAscendingByDefault: $sortAscendingByDefault,
        );
    }

    public static function createDefaultColumn(
        string $identifier,
        string $label,
        bool $isSortable = false,
        bool $sortAscendingByDefault = true,
    ): DefaultColumn {
        return new DefaultColumn(
            identifier: $identifier,
            label: $label,
            isSortable: $isSortable,
            sortAscendingByDefault: $sortAscendingByDefault,
        );
    }

    /**
     * @param array<int|string, HtmlText|string> $options Value of the column => label (text, or `HtmlText`)
     */
    public static function createOptionsColumn(
        string $identifier,
        string $label,
        array $options,
        bool $isSortable,
        bool $sortAscendingByDefault = true,
    ): OptionsColumn {
        return new OptionsColumn(
            identifier: $identifier,
            label: $label,
            options: $options,
            isSortable: $isSortable,
            sortAscendingByDefault: $sortAscendingByDefault,
        );
    }

    /**
     * @param callable(TableItem): string $callbackFunction Returns the HTML of the cell, see `CallbackColumn`
     */
    public static function createCallbackColumn(
        string $identifier,
        string $label,
        callable $callbackFunction,
        bool $isSortable = false,
        bool $sortAscendingByDefault = true,
    ): CallbackColumn {
        return new CallbackColumn(
            identifier: $identifier,
            label: $label,
            callbackFunction: $callbackFunction,
            isSortable: $isSortable,
            sortAscendingByDefault: $sortAscendingByDefault,
        );
    }
}
