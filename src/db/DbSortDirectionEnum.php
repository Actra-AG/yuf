<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

/**
 * @internal
 */
enum DbSortDirectionEnum: string
{
    case ASC = 'ASC';
    case DESC = 'DESC';

    public static function fromAscending(bool $ascending): DbSortDirectionEnum
    {
        return $ascending ? DbSortDirectionEnum::ASC : DbSortDirectionEnum::DESC;
    }
}
