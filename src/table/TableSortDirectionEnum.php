<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table;

/**
 * The value is the one in the sort links (`?sort=<table>|<column>|ASC`) and in the session.
 */
enum TableSortDirectionEnum: string
{
    case ASC = 'ASC';
    case DESC = 'DESC';

    public static function fromAscending(bool $ascending): TableSortDirectionEnum
    {
        return $ascending ? TableSortDirectionEnum::ASC : TableSortDirectionEnum::DESC;
    }

    public function opposite(): TableSortDirectionEnum
    {
        return match ($this) {
            TableSortDirectionEnum::ASC => TableSortDirectionEnum::DESC,
            TableSortDirectionEnum::DESC => TableSortDirectionEnum::ASC,
        };
    }

    public function isAscending(): bool
    {
        return $this === TableSortDirectionEnum::ASC;
    }
}
