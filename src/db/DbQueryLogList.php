<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;

/**
 * The queries a `FrameworkDb` has executed with logging switched on (see `FrameworkDb::getQueryLog()`). One list per
 * connection; the log lives as long as the connection.
 *
 * @phpstan-import-type SqlParameters from DbQueryData
 */
final class DbQueryLogList
{
    /** @var list<DbQueryLogItem> */
    private array $items = [];

    public function __construct(private readonly Clock $clock = new SystemClock()) {}

    /**
     * Starts the time measurement of a query. The item gets part of the list with `add()`.
     *
     * @param SqlParameters $params
     */
    public function start(string $sqlQuery, array $params): DbQueryLogItem
    {
        return new DbQueryLogItem(sqlQuery: $sqlQuery, params: $params, clock: $this->clock);
    }

    public function add(DbQueryLogItem $dbQueryLogItem): void
    {
        $this->items[] = $dbQueryLogItem;
    }

    /**
     * @return list<DbQueryLogItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
