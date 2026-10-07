<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use actra\yuf\table\TableItem;
use Override;

class CallbackColumn extends AbstractTableColumn
{
    /** @var callable */
    private $callbackFunction;

    public function __construct(
        string $identifier,
        string $label,
        callable $callbackFunction,
        bool $isSortable = false,
        bool $sortAscendingByDefault = true,
    ) {
        $this->callbackFunction = $callbackFunction;
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
        return call_user_func(
            $this->callbackFunction,
            $tableItem,
        ); // TODO: Named parameters not working in PHP 8.0
    }
}
