<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\table;

use actra\yuf\table\column\AbstractTableColumn;
use actra\yuf\table\renderer\TableHeadRenderer;
use actra\yuf\table\TableItem;
use actra\yuf\table\TableItemCollection;
use LogicException;

// Can be extended or used directly to render a table with data from different sources
class SmartTable
{
    public const string TOTAL_AMOUNT = '[totalAmount]';
    public const string TABLE = '[table]';
    public const string TABLE_HEADER = '[tableHeader]';
    public const string TABLE_BODY = '[tableBody]';
    public const string CELLS = '[cells]';

    public const string TOTAL_AMOUNT_MESSAGE_PLACEHOLDER = '[TOTAL_AMOUNT_MESSAGE]';
    public const string AMOUNT = '[AMOUNT]';
    /** @var SmartTable[] */
    private static array $instances = [];
    public string $noDataHtml = '<p class="no-entry">Es wurden keine Einträge gefunden.</p>';
    public string $totalAmountHtml = '<p class="search-result">' . SmartTable::TOTAL_AMOUNT_MESSAGE_PLACEHOLDER . '</p>';
    public string $fullHtml = '<div class="table-meta table-meta-header">' . SmartTable::TOTAL_AMOUNT . '</div><div class="table-wrap">' . SmartTable::TABLE . '</div>';
    public string $tableHtml = '<thead>' . SmartTable::TABLE_HEADER . '</thead><tbody>' . SmartTable::TABLE_BODY . '</tbody>';
    public string $oddRowHtml = '<tr>' . SmartTable::CELLS . '</tr>';
    public string $evenRowHtml = '<tr>' . SmartTable::CELLS . '</tr>';
    public string $totalAmountMessage_oneResult = 'Es wurde <strong>1</strong> Resultat gefunden.';
    public string $totalAmountMessage_numResults = 'Es wurden <strong>' . SmartTable::AMOUNT . '</strong> Resultate gefunden.';
    /** @var AbstractTableColumn[] */
    public private(set) array $columns = [];
    private array $cssClasses = ['table'];

    public function __construct(
        public readonly string $identifier,
        private readonly TableHeadRenderer $tableHeadRenderer,
        public readonly TableItemCollection $tableItemCollection,
    ) {
        if (array_key_exists(key: $identifier, array: SmartTable::$instances)) {
            throw new LogicException(message: 'There is already a table with the same identifier ' . $identifier);
        }
        SmartTable::$instances[$this->identifier] = $this;
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
        if ($totalAmountOfItems === 1) {
            $totalAmountMessage = $this->totalAmountMessage_oneResult;
        } else {
            $totalAmountMessage = str_replace(
                search: SmartTable::AMOUNT,
                replace: number_format(num: $totalAmountOfItems, thousands_separator: '\''),
                subject: $this->totalAmountMessage_numResults,
            );
        }
        $bodyArr = [];
        $rowNumber = 0;
        foreach ($this->tableItemCollection->list() as $tableItem) {
            $rowNumber++;
            $cells = [];
            foreach ($this->columns as $abstractTableColumn) {
                $cells[] = $abstractTableColumn->renderCell(tableItem: $tableItem);
            }
            $rowHtml = (($rowNumber % 2) === 0) ? $this->evenRowHtml : $this->oddRowHtml;
            $bodyArr[] = str_replace(
                search: SmartTable::CELLS,
                replace: implode(separator: PHP_EOL, array: $cells),
                subject: $rowHtml,
            );
        }
        $tableAttributes = ['table'];
        if (count(value: $this->cssClasses) > 0) {
            $tableAttributes[] = 'class="' . implode(separator: ' ', array: $this->cssClasses) . '"';
        }
        $tableHtml = str_replace(
            search: [
                SmartTable::TABLE_HEADER,
                SmartTable::TABLE_BODY,
            ],
            replace: [
                $this->tableHeadRenderer->render(smartTable: $this),
                implode(separator: PHP_EOL, array: $bodyArr),
            ],
            subject: $this->tableHtml,
        );

        $placeholders = [
            SmartTable::TOTAL_AMOUNT => str_replace(
                search: SmartTable::TOTAL_AMOUNT_MESSAGE_PLACEHOLDER,
                replace: $totalAmountMessage,
                subject: $this->totalAmountHtml,
            ),
            SmartTable::TABLE => implode(
                separator: PHP_EOL,
                array: [
                    '<' . implode(
                        separator: ' ',
                        array: $tableAttributes,
                    ) . '>',
                    $tableHtml,
                    '</table>',
                ],
            ),
        ];

        $srcArr = array_keys(array: $placeholders);
        $rplArr = array_values(array: $placeholders);

        return ($totalAmountOfItems === 0) ? str_replace(
            search: $srcArr,
            replace: $rplArr,
            subject: $this->noDataHtml,
        ) : str_replace(
            search: $srcArr,
            replace: $rplArr,
            subject: $this->fullHtml,
        );
    }

    public function getTotalAmount(): int
    {
        return $this->tableItemCollection->count();
    }
}
