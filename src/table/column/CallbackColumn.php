<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use actra\yuf\db\DbRow;
use actra\yuf\html\HtmlText;
use actra\yuf\table\TableItem;
use Closure;
use LogicException;
use Override;

/**
 * The callback returns the HTML of the cell as it is output: it must encode values (`TableItem::renderValue()` does).
 * `DbResultTable::exportCsv()` exports the column only with an export value (e.g. the name of a linked name).
 */
final class CallbackColumn extends AbstractTableColumn
{
    /** @var Closure(TableItem): string */
    private readonly Closure $callbackFunction;
    /** @var Closure(DbRow): (bool|float|int|string|null)|null */
    private readonly ?Closure $exportValueFunction;

    /**
     * @param callable(TableItem): string $callbackFunction
     * @param null|callable(DbRow): (bool|float|int|string|null) $exportValueFunction The value of the cell in the CSV
     *     export (`NULL` is an empty cell); `null` leaves the column out of the export
     */
    public function __construct(
        string $identifier,
        HtmlText $label,
        callable $callbackFunction,
        bool $isSortable = false,
        bool $sortAscendingByDefault = true,
        ?callable $exportValueFunction = null,
    ) {
        $this->callbackFunction = Closure::fromCallable(callback: $callbackFunction);
        $this->exportValueFunction = $exportValueFunction === null
            ? null
            : Closure::fromCallable(callback: $exportValueFunction);
        parent::__construct(
            identifier: $identifier,
            label: $label,
            isSortable: $isSortable,
            sortAscendingByDefault: $sortAscendingByDefault,
        );
    }

    #[Override]
    protected function renderCellValue(TableItem $tableItem): string
    {
        return ($this->callbackFunction)($tableItem);
    }

    public function isExported(): bool
    {
        return $this->exportValueFunction !== null;
    }

    /**
     * @throws LogicException If the column has no export value (`isExported()`)
     */
    public function getExportValue(DbRow $row): bool|float|int|string|null
    {
        if ($this->exportValueFunction === null) {
            throw new LogicException(message: 'Column "' . $this->identifier . '" has no export value.');
        }

        return ($this->exportValueFunction)($row);
    }
}
