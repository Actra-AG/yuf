<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use actra\yuf\table\TableItem;
use Closure;
use Override;

/**
 * The callback returns the HTML of the cell as it is output: it must encode values (`TableItem::renderValue()` does).
 */
final class CallbackColumn extends AbstractTableColumn
{
    /** @var Closure(TableItem): string */
    private readonly Closure $callbackFunction;

    /**
     * @param callable(TableItem): string $callbackFunction
     */
    public function __construct(
        string $identifier,
        string $label,
        callable $callbackFunction,
        bool $isSortable = false,
        bool $sortAscendingByDefault = true,
    ) {
        $this->callbackFunction = Closure::fromCallable(callback: $callbackFunction);
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
}
