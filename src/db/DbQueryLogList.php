<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

final class DbQueryLogList
{
    /** @var DbQueryLogItem[] */
    private static array $stack = [];

    public static function add(DbQueryLogItem $dbQueryLogItem): void
    {
        DbQueryLogList::$stack[] = $dbQueryLogItem;
    }

    public static function getLog(): array
    {
        return DbQueryLogList::$stack;
    }
}