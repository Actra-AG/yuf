<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\table;

use actra\yuf\html\HtmlEncoder;
use actra\yuf\table\column\AbstractTableColumn;
use actra\yuf\table\renderer\TableHeadRenderer;
use actra\yuf\table\TableItem;
use actra\yuf\table\TableItemCollection;
use actra\yuf\table\TableMessages;
use LogicException;

/**
 * Extension point: used directly it renders a table with data from any source, `DbResultTable` extends it. The
 * identifier must be unique per page (it is the key of the table state in the session).
 *
 * The texts come from `TableMessages` (German by default). The HTML properties are templates of the application with
 * the placeholders of the constants; the content of the cells is inserted into them in one pass, so a value is never
 * read as placeholder.
 */
class SmartTable
{
    public const string TOTAL_AMOUNT = '[totalAmount]';
    public const string TABLE = '[table]';
    public const string TABLE_HEADER = '[tableHeader]';
    public const string TABLE_BODY = '[tableBody]';
    public const string CELLS = '[cells]';

    public const string TOTAL_AMOUNT_MESSAGE_PLACEHOLDER = '[TOTAL_AMOUNT_MESSAGE]';
    public const string AMOUNT = '[AMOUNT]';
    public string $totalAmountHtml = '<p class="search-result">'
        . SmartTable::TOTAL_AMOUNT_MESSAGE_PLACEHOLDER . '</p>';
    public string $fullHtml = '<div class="table-meta table-meta-header">'
        . SmartTable::TOTAL_AMOUNT . '</div><div class="table-wrap">' . SmartTable::TABLE . '</div>';
    public string $tableHtml = '<thead>'
        . SmartTable::TABLE_HEADER . '</thead><tbody>' . SmartTable::TABLE_BODY . '</tbody>';
    public string $oddRowHtml = '<tr>' . SmartTable::CELLS . '</tr>';
    public string $evenRowHtml = '<tr>' . SmartTable::CELLS . '</tr>';
    /** @var array<string, AbstractTableColumn> */
    public private(set) array $columns = [];
    /** @var list<string> */
    private array $cssClasses = ['table'];
    private readonly string $noDataHtml;
    private readonly string $totalAmountMessageOneResult;
    private readonly string $totalAmountMessageNumResults;

    public function __construct(
        public readonly string $identifier,
        private readonly TableHeadRenderer $tableHeadRenderer,
        public readonly TableItemCollection $tableItemCollection,
        TableMessages $messages = new TableMessages(),
    ) {
        $this->noDataHtml = '<p class="no-entry">' . HtmlEncoder::encode(value: $messages->noData) . '</p>';
        $this->totalAmountMessageOneResult = SmartTable::renderAmountMessage(
            message: $messages->oneResult,
            amount: '1',
        );
        $this->totalAmountMessageNumResults = SmartTable::renderAmountMessage(
            message: $messages->numResults,
            amount: SmartTable::AMOUNT,
        );
    }

    /**
     * The encoded message with the amount in `<strong>`.
     */
    private static function renderAmountMessage(string $message, string $amount): string
    {
        return strtr(
            string: HtmlEncoder::encode(value: $message),
            from: [TableMessages::AMOUNT_PLACEHOLDER => '<strong>' . $amount . '</strong>'],
        );
    }

    public function addCssClass(string $className): void
    {
        $this->cssClasses[] = $className;
    }

    public function addColumn(AbstractTableColumn $abstractTableColumn): void
    {
        $columnIdentifier = $abstractTableColumn->identifier;
        if (array_key_exists(
            key: $columnIdentifier,
            array: $this->columns,
        )) {
            throw new LogicException(
                message: 'There is already a column with the same identifier ' . $columnIdentifier,
            );
        }
        $abstractTableColumn->tableIdentifier = $this->identifier;
        $this->columns[$columnIdentifier] = $abstractTableColumn;
    }

    public function addDataItem(TableItem $tableItem): void
    {
        $this->tableItemCollection->add(tableItem: $tableItem);
    }

    public function render(): string
    {
        $totalAmountOfItems = $this->getTotalAmount();
        $totalAmountMessage = $this->renderTotalAmountMessage(totalAmount: $totalAmountOfItems);
        $placeholders = [
            SmartTable::TOTAL_AMOUNT => strtr(
                string: $this->totalAmountHtml,
                from: [SmartTable::TOTAL_AMOUNT_MESSAGE_PLACEHOLDER => $totalAmountMessage],
            ),
            SmartTable::TABLE => $this->renderTable(),
        ] + $this->getTemplatePlaceholders();

        return strtr(
            string: $totalAmountOfItems === 0 ? $this->getNoDataHtml() : $this->fullHtml,
            from: $placeholders,
        );
    }

    public function getTotalAmount(): int
    {
        return $this->tableItemCollection->count();
    }

    /**
     * The HTML of the table without rows (a template with the placeholders of `getTemplatePlaceholders()`).
     */
    protected function getNoDataHtml(): string
    {
        return $this->noDataHtml;
    }

    /**
     * More placeholders for `fullHtml` and the HTML of the table without rows (placeholder => HTML). Called by `render()` after the total
     * amount is known; the values are not searched for placeholders.
     *
     * @return array<string, string>
     */
    protected function getTemplatePlaceholders(): array
    {
        return [];
    }

    private function renderTotalAmountMessage(int $totalAmount): string
    {
        if ($totalAmount === 1) {
            return $this->totalAmountMessageOneResult;
        }

        return strtr(
            string: $this->totalAmountMessageNumResults,
            from: [SmartTable::AMOUNT => number_format(num: $totalAmount, thousands_separator: '\'')],
        );
    }

    private function renderTable(): string
    {
        $bodyArr = [];
        $rowNumber = 0;
        foreach ($this->tableItemCollection->list() as $tableItem) {
            $rowNumber++;
            $cells = [];
            foreach ($this->columns as $abstractTableColumn) {
                $cells[] = $abstractTableColumn->renderCell(tableItem: $tableItem);
            }
            $bodyArr[] = strtr(
                string: ($rowNumber % 2) === 0 ? $this->evenRowHtml : $this->oddRowHtml,
                from: [SmartTable::CELLS => implode(separator: PHP_EOL, array: $cells)],
            );
        }
        $tableHtml = strtr(
            string: $this->tableHtml,
            from: [
                SmartTable::TABLE_HEADER => $this->tableHeadRenderer->render(smartTable: $this),
                SmartTable::TABLE_BODY => implode(separator: PHP_EOL, array: $bodyArr),
            ],
        );

        return implode(
            separator: PHP_EOL,
            array: [
                '<table class="' . implode(separator: ' ', array: $this->cssClasses) . '">',
                $tableHtml,
                '</table>',
            ],
        );
    }
}
