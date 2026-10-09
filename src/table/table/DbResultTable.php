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
use actra\yuf\table\TableItem;
use actra\yuf\table\TableItemCollection;
use actra\yuf\table\TableSessionState;
use actra\yuf\table\TableSortDirectionEnum;
use actra\yuf\template\TemplateEngine;
use InvalidArgumentException;
use Override;

/**
 * Extension point (`actra/backend` extends it for its tables): a table of the result of a database query, with sorting,
 * pagination and an optional filter. Sorting and page come from the query string of the request (never from posted
 * data), the sort column only from the sortable columns of this table, and are kept in the session.
 */
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
    /** @var array<string, string> Names and values as they are in the request; the links encode them */
    public private(set) array $additionalLinkParameters = [];
    private ?int $totalAmount = null;
    private bool $filledDataBySelectQuery = false;
    private ?AbstractTableColumn $defaultSortColumn = null;
    /** Whether the current sorting has been chosen by the user instead of being the default one. */
    private bool $hasUserDefinedSorting = false;
    private TablePaginationRenderer $tablePaginationRenderer;
    private int $filledAmount = 0;
    private readonly TableSessionState $state;

    /**
     * @param string $identifier Can be the name of the main table but must be unique per page
     * @param Session $session Keeps sorting and page of the user (`ViewContext::$session`)
     * @param int $itemsPerPage Rows per page, at least 1
     * @param bool $limitToOnePage Whether a result that does not fit on one page is cut instead of paginated
     */
    public function __construct(
        string $identifier,
        public readonly FrameworkDb $db,
        public readonly DbQuery $dbQuery,
        private readonly TemplateEngine $templateEngine,
        private readonly HttpRequest $httpRequest,
        Session $session,
        private readonly ?TableFilter $tableFilter = null,
        ?TablePaginationRenderer $tablePaginationRenderer = null,
        ?SortableTableHeadRenderer $sortableTableHeadRenderer = null,
        private readonly int $itemsPerPage = 25,
        public bool $limitToOnePage = false,
    ) {
        if ($itemsPerPage < 1) {
            throw new InvalidArgumentException(
                message: 'Items per page of table "' . $identifier . '" must be at least 1, '
                . $itemsPerPage . ' given.',
            );
        }
        parent::__construct(
            identifier: $identifier,
            tableHeadRenderer: $sortableTableHeadRenderer ?? new SortableTableHeadRenderer(),
            tableItemCollection: new TableItemCollection(),
        );
        $this->noDataHtml = DbResultTable::FILTER . $this->noDataHtml;
        $this->fullHtml = DbResultTable::FILTER . '<div class="table-meta table-meta-header">'
            . SmartTable::TOTAL_AMOUNT . DbResultTable::PAGINATION . '</div><div class="table-wrap">'
            . SmartTable::TABLE . '</div>' . DbResultTable::TABLE_FOOTER;
        $this->state = new TableSessionState(session: $session, section: SessionSectionEnum::TABLES);
        $this->tablePaginationRenderer = $tablePaginationRenderer ?? new TablePaginationRenderer();
    }

    #[Override]
    public function addColumn(AbstractTableColumn $abstractTableColumn, bool $isDefaultSortColumn = false): void
    {
        parent::addColumn(abstractTableColumn: $abstractTableColumn);

        if ($isDefaultSortColumn) {
            $this->defaultSortColumn = $abstractTableColumn;
        }
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function getTemplatePlaceholders(): array
    {
        $pagination = $this->tablePaginationRenderer->render(
            dbResultTable: $this,
            templateEngine: $this->templateEngine,
            entriesPerPage: $this->itemsPerPage,
        );

        return [
            DbResultTable::FILTER => $this->tableFilter?->render(templateEngine: $this->templateEngine) ?? '',
            DbResultTable::PAGINATION => $pagination,
            DbResultTable::TABLE_FOOTER => $pagination === ''
                ? ''
                : '<div class="table-meta table-meta-footer">' . $pagination . '</div>',
        ];
    }

    public function fillBySelectQuery(): void
    {
        if ($this->filledDataBySelectQuery) {
            return;
        }

        $this->tableFilter?->validate(dbResultTable: $this);
        $this->initSorting();
        $this->initPaginationPage();

        $sortColumn = $this->getCurrentSortColumn();
        if ($sortColumn !== null && $sortColumn !== '') {
            if ($this->hasUserDefinedSorting) {
                // A sorting which has been chosen by the user replaces the one of the given DbQuery
                // (e.g. a sorting by the relevance of a fulltext search).
                $this->dbQuery->clearOrderParts();
            }
            $this->dbQuery->addOrderPart(
                column: $sortColumn,
                ascending: $this->getCurrentSortDirection()->isAscending(),
            );
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
        $requestedSorting = $this->readRequestedSorting();
        if ($requestedSorting !== null) {
            [$requestedSortColumn, $requestedSortDirection] = $requestedSorting;
            $this->state->set(
                identifier: $this->identifier,
                index: DbResultTable::SORT_COLUMN_KEY,
                value: $requestedSortColumn,
            );
            $this->state->set(
                identifier: $this->identifier,
                index: DbResultTable::SORT_DIRECTION_KEY,
                value: $requestedSortDirection->value,
            );
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
     * The sorting of the request (`sort=<table>|<column>|<direction>`) if it is valid for this table: the column has to
     * be one of its sortable columns (a whitelist, the value is never used otherwise).
     *
     * @return array{string, TableSortDirectionEnum}|null
     */
    private function readRequestedSorting(): ?array
    {
        $requestedSorting = trim(string: (string) $this->httpRequest->getQueryString(name: DbResultTable::PARAM_SORT));
        $parts = explode(separator: '|', string: $requestedSorting);
        if (count(value: $parts) !== 3) {
            return null;
        }
        [$requestedTable, $requestedColumn, $requestedDirection] = $parts;
        $direction = TableSortDirectionEnum::tryFrom(value: $requestedDirection);
        if ($requestedTable !== $this->identifier || $direction === null) {
            return null;
        }
        foreach ($this->columns as $abstractTableColumn) {
            if ($abstractTableColumn->isSortable && $abstractTableColumn->identifier === $requestedColumn) {
                return [$abstractTableColumn->identifier, $direction];
            }
        }

        return null;
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
        // A page whose offset does not fit into an integer cannot exist
        $lastPossiblePage = intdiv(num1: PHP_INT_MAX, num2: $this->itemsPerPage);
        if ($inputTable === $this->identifier && $inputPage > 0 && $inputPage <= $lastPossiblePage) {
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
     * The sort direction: the choice of the user, else the one of the default sort column (ascending if the column
     * does not say otherwise).
     */
    public function getCurrentSortDirection(): TableSortDirectionEnum
    {
        $storedDirection = $this->state->get(identifier: $this->identifier, index: DbResultTable::SORT_DIRECTION_KEY);
        if ($storedDirection !== null && $this->getStoredSortColumn() !== null) {
            $direction = TableSortDirectionEnum::tryFrom(value: $storedDirection);
            if ($direction !== null) {
                return $direction;
            }
        }

        return TableSortDirectionEnum::fromAscending(
            ascending: $this->getDefaultSortColumn()?->sortAscendingByDefault !== false,
        );
    }

    /**
     * @param string $key Name of a parameter of the links (not encoded)
     * @param string $value Not encoded
     */
    public function addAdditionalLinkParameter(string $key, string $value): void
    {
        $this->additionalLinkParameters[$key] = $value;
    }

    #[Override]
    public function getTotalAmount(): int
    {
        if ($this->totalAmount !== null) {
            return $this->totalAmount;
        }

        $this->fillBySelectQuery();

        if ($this->limitToOnePage) {
            return $this->totalAmount = $this->filledAmount;
        }
        // A page that is not full is the last one: the total is known without a COUNT query (an empty page after the
        // first one may be beyond the end, so it is counted)
        $currentPage = $this->getCurrentPaginationPage();
        if ($this->filledAmount < $this->itemsPerPage && ($currentPage === 1 || $this->filledAmount > 0)) {
            return $this->totalAmount = ($currentPage - 1) * $this->itemsPerPage + $this->filledAmount;
        }

        return $this->totalAmount = $this->dbQuery->getTotalAmount(db: $this->db);
    }
}
