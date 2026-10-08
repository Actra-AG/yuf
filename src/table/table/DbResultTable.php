<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\table;

use actra\yuf\core\HttpRequest;
use actra\yuf\db\DbQuery;
use actra\yuf\db\FrameworkDb;
use actra\yuf\session\Session;
use actra\yuf\session\SessionSectionEnum;
use actra\yuf\table\column\AbstractTableColumn;
use actra\yuf\table\filter\TableFilter;
use actra\yuf\table\renderer\SortableTableHeadRenderer;
use actra\yuf\table\renderer\TablePaginationRenderer;
use actra\yuf\table\TableHelper;
use actra\yuf\table\TableItem;
use actra\yuf\table\TableItemCollection;
use actra\yuf\table\TableSessionState;
use actra\yuf\template\TemplateEngine;
use Override;

class DbResultTable extends SmartTable
{
    protected const string PARAM_SORT = 'sort';
    protected const string PARAM_RESET = 'reset';
    protected const string PARAM_PAGE = 'page';
    public const string PARAM_FIND = 'find';

    private const string SORT_COLUMN_KEY = 'sortColumn';
    private const string SORT_DIRECTION_KEY = 'sortDirection';
    private const string PAGINATION_PAGE_KEY = 'paginationPage';
    protected const string FILTER = '[filter]';
    protected const string PAGINATION = '[pagination]';
    protected const string TABLE_FOOTER = '[footer]';
    public private(set) array $additionalLinkParameters = [];
    private ?int $totalAmount = null;
    private bool $filledDataBySelectQuery = false;
    private ?AbstractTableColumn $defaultSortColumn = null;
    /** Whether the current sorting has been chosen by the user instead of being the default one. */
    private bool $hasUserDefinedSorting = false;
    private TablePaginationRenderer $tablePaginationRenderer;
    private ?int $filledAmount = null;
    private readonly TableSessionState $state;

    /**
     * @param Session $session Keeps sorting and page of the user (`ViewContext::$session`)
     */
    public function __construct(
        string                          $identifier, // Can be the name of the main table but must be unique per page
        public readonly FrameworkDb     $db,
        public readonly DbQuery         $dbQuery,
        private readonly TemplateEngine $templateEngine,
        private readonly HttpRequest    $httpRequest,
        Session                         $session,
        private readonly ?TableFilter   $tableFilter = null,
        ?TablePaginationRenderer        $tablePaginationRenderer = null,
        ?SortableTableHeadRenderer      $sortableTableHeadRenderer = null,
        private readonly int            $itemsPerPage = 25,
        // Max rows in the table before pagination starts, if a result is not limited to one page
        public bool                     $limitToOnePage = false,
    ) {
        if ($sortableTableHeadRenderer === null) {
            $sortableTableHeadRenderer = new SortableTableHeadRenderer();
        }
        parent::__construct(
            identifier: $identifier,
            tableHeadRenderer: $sortableTableHeadRenderer,
            tableItemCollection: new TableItemCollection(),
        );
        $this->noDataHtml = DbResultTable::FILTER . $this->noDataHtml;
        $this->fullHtml = DbResultTable::FILTER . '<div class="table-meta table-meta-header">' . SmartTable::TOTAL_AMOUNT . DbResultTable::PAGINATION . '</div><div class="table-wrap">' . SmartTable::TABLE . '</div>' . DbResultTable::TABLE_FOOTER;
        $this->state = new TableSessionState(session: $session, section: SessionSectionEnum::TABLES);
        $this->tablePaginationRenderer = $tablePaginationRenderer === null ? new TablePaginationRenderer() : $tablePaginationRenderer;
    }

    #[Override]
    public function addColumn(AbstractTableColumn $abstractTableColumn, bool $isDefaultSortColumn = false): void
    {
        parent::addColumn(abstractTableColumn: $abstractTableColumn);

        if ($isDefaultSortColumn) {
            $this->defaultSortColumn = $abstractTableColumn;
        }
    }

    #[Override]
    public function render(): string
    {
        $this->fillBySelectQuery();
        $pagination = $this->tablePaginationRenderer->render(
            dbResultTable: $this,
            templateEngine: $this->templateEngine,
            entriesPerPage: $this->itemsPerPage,
        );
        $placeholders = [
            DbResultTable::FILTER => $this->tableFilter === null ? '' : $this->tableFilter->render(templateEngine: $this->templateEngine),
            DbResultTable::PAGINATION => $pagination,
            DbResultTable::TABLE_FOOTER => ($pagination === '') ? '' : '<div class="table-meta table-meta-footer">' . $pagination . '</div>',
        ];

        return str_replace(
            search: array_keys(array: $placeholders),
            replace: array_values(array: $placeholders),
            subject: parent::render(),
        );
    }

    public function fillBySelectQuery(): void
    {
        if ($this->filledDataBySelectQuery) {
            return;
        }

        if ($this->tableFilter !== null) {
            $this->tableFilter->validate(dbResultTable: $this);
        }
        $this->initSorting();
        $this->initPaginationPage();

        $sortColumn = $this->getCurrentSortColumn();
        $sortDirection = $this->getCurrentSortDirection();
        if ((string) $sortColumn !== '') {
            if ($this->hasUserDefinedSorting) {
                // A sorting which has been chosen by the user replaces the one of the given DbQuery
                // (e.g. a sorting by the relevance of a fulltext search).
                $this->dbQuery->clearOrderParts();
            }
            $this->dbQuery->addOrderPart(column: $sortColumn, ascending: ($sortDirection !== TableHelper::SORT_DESC));
        }
        $res = $this->dbQuery->selectFromDb(
            db: $this->db,
            offset: ($this->getCurrentPaginationPage() - 1) * $this->itemsPerPage,
            rowCount: $this->itemsPerPage,
        );
        foreach ($res as $dataItem) {
            $this->addDataItem(tableItem: new TableItem(dataObject: $dataItem));
        }
        $this->filledDataBySelectQuery = true;
        $this->filledAmount = count(value: $res);
    }

    private function initSorting(): void
    {
        $availableSortOptions = [];
        foreach ($this->columns as $abstractTableColumn) {
            if ($abstractTableColumn->isSortable) {
                $availableSortOptions[] = $abstractTableColumn->identifier;
            }
        }

        $requestedSorting = trim(string: (string) $this->httpRequest->getQueryString(name: DbResultTable::PARAM_SORT));
        if ($requestedSorting !== '') {
            $requestedSortingArr = explode(separator: '|', string: $requestedSorting);
            if (count(value: $requestedSortingArr) === 3) {
                $requestedSortTable = $requestedSortingArr[0];
                $requestedSortColumn = $requestedSortingArr[1];
                $requestedSortDirection = $requestedSortingArr[2];

                if (
                    $requestedSortTable === $this->identifier
                    && in_array(needle: $requestedSortColumn, haystack: $availableSortOptions, strict: true)
                    && array_key_exists(key: $requestedSortDirection, array: TableHelper::OPPOSITE_SORT_DIRECTION)
                ) {
                    $this->state->set(
                        identifier: $this->identifier,
                        index: DbResultTable::SORT_COLUMN_KEY,
                        value: $requestedSortColumn,
                    );
                    $this->state->set(
                        identifier: $this->identifier,
                        index: DbResultTable::SORT_DIRECTION_KEY,
                        value: $requestedSortDirection,
                    );
                }
            }
        }

        if ($this->httpRequest->getQueryString(name: DbResultTable::PARAM_RESET) !== null) {
            // Back to the default sorting: the defaults are not stored
            $this->state->remove(identifier: $this->identifier, index: DbResultTable::SORT_COLUMN_KEY);
            $this->state->remove(identifier: $this->identifier, index: DbResultTable::SORT_DIRECTION_KEY);
        }
        // The sorting has been chosen by the user, either within this request or a previous one.
        $this->hasUserDefinedSorting = $this->getStoredSortColumn() !== null;
    }

    /**
     * The column the table is sorted by: the choice of the user, else the default sort column (the first column if
     * none is defined as default); `null` for a table without columns.
     */
    public function getCurrentSortColumn(): ?string
    {
        return $this->getStoredSortColumn() ?? $this->getDefaultSortColumn()?->identifier;
    }

    private function getStoredSortColumn(): ?string
    {
        $storedSortColumn = $this->state->get(identifier: $this->identifier, index: DbResultTable::SORT_COLUMN_KEY);

        return $storedSortColumn === '' ? null : $storedSortColumn;
    }

    private function getDefaultSortColumn(): ?AbstractTableColumn
    {
        return $this->defaultSortColumn ?? ($this->columns === [] ? null : current(array: $this->columns));
    }

    private function initPaginationPage(): void
    {
        $inputPageArr = explode(
            separator: '|',
            string: trim(
                string: (string) $this->httpRequest->getQueryString(name: DbResultTable::PARAM_PAGE),
            ),
        );
        $inputPage = (int) $inputPageArr[0];
        $inputTable = trim(string: array_key_exists(key: 1, array: $inputPageArr) ? $inputPageArr[1] : '');
        if ($inputTable === $this->identifier && $inputPage > 0) {
            $this->setCurrentPaginationPage(page: $inputPage);
        }

        if (
            $this->httpRequest->getQueryString(name: DbResultTable::PARAM_FIND) !== null
            || $this->httpRequest->getQueryString(name: DbResultTable::PARAM_RESET) !== null
        ) {
            $this->setCurrentPaginationPage(page: 1);
        }
    }

    public function setCurrentPaginationPage(int $page): void
    {
        // The first page is the default, it needs no entry in the session
        if ($page === $this->getCurrentPaginationPage()) {
            return;
        }
        $this->state->set(
            identifier: $this->identifier,
            index: DbResultTable::PAGINATION_PAGE_KEY,
            value: (string) $page,
        );
    }

    /**
     * The page of the user, 1 if none is stored.
     */
    public function getCurrentPaginationPage(): int
    {
        $storedPage = (int) $this->state->get(identifier: $this->identifier, index: DbResultTable::PAGINATION_PAGE_KEY);

        return max(1, $storedPage);
    }

    /**
     * The sort direction (`TableHelper::SORT_ASC` / `SORT_DESC`): the choice of the user, else the one of the default
     * sort column (ascending if the column does not say otherwise).
     */
    public function getCurrentSortDirection(): string
    {
        $storedDirection = $this->state->get(identifier: $this->identifier, index: DbResultTable::SORT_DIRECTION_KEY);
        if ($storedDirection !== null && $this->getStoredSortColumn() !== null) {
            return $storedDirection;
        }

        return $this->getDefaultSortColumn()?->sortAscendingByDefault === false
            ? TableHelper::SORT_DESC
            : TableHelper::SORT_ASC;
    }

    public function addAdditionalLinkParameter(string $key, string $value): void
    {
        $this->additionalLinkParameters[urlencode(string: $key)] = urlencode(string: $value);
    }

    #[Override]
    public function getTotalAmount(): int
    {
        if ($this->totalAmount !== null) {
            return $this->totalAmount;
        }

        $this->fillBySelectQuery();

        if (($this->getCurrentPaginationPage() === 1 && $this->filledAmount < $this->itemsPerPage) || $this->limitToOnePage) {
            return $this->totalAmount = $this->filledAmount;
        }

        return $this->totalAmount = $this->dbQuery->getTotalAmount(db: $this->db);
    }
}
