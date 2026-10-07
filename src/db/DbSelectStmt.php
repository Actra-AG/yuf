<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

use PDO;
use PDOStatement;
use RuntimeException;
use stdClass;
use Throwable;

class DbSelectStmt
{
    private PDOStatement $pdoStatement;
    private bool $logQuery;

    public function __construct(PDOStatement $pdoStatement, bool $logQuery = false)
    {
        $this->pdoStatement = $pdoStatement;
        $this->logQuery = $logQuery;
    }

    // The classic PDOStatement has execute() and fetch(). It was desired to mimic the
    // effect of FrameworkDb->select(), which does both on ONE call.
    /**
     * @param list<mixed> $parameters
     *
     * @return list<stdClass>
     */
    public function ExecuteAndFetch(array $parameters): array
    {
        try {
            if ($this->logQuery) {
                $dbQueryLogItem = new DbQueryLogItem($this->pdoStatement->queryString, $parameters);
            }
            if ($this->pdoStatement->execute($parameters) === false) {
                throw new RuntimeException(message: 'PDOStatement->execute() returned false');
            }
            /** @var list<stdClass> $res PDO::fetchAll() is untyped, FETCH_OBJ guarantees this shape */
            $res = $this->pdoStatement->fetchAll(mode: PDO::FETCH_OBJ);
            if (isset($dbQueryLogItem)) {
                $dbQueryLogItem->confirmFinishedExecution();
                DbQueryLogList::add($dbQueryLogItem);
            }

            return $res;
        } catch (Throwable $t) {
            throw new DbRuntimeException($t, $this->pdoStatement->queryString, $parameters);
        }
    }

    /**
     * Like ExecuteAndFetch(), but returns typed rows.
     *
     * @param list<mixed> $parameters
     *
     * @return list<DbRow>
     */
    public function executeAndFetchRows(array $parameters): array
    {
        try {
            if ($this->logQuery) {
                $dbQueryLogItem = new DbQueryLogItem($this->pdoStatement->queryString, $parameters);
            }
            if ($this->pdoStatement->execute($parameters) === false) {
                throw new RuntimeException(message: 'PDOStatement->execute() returned false');
            }
            /** @var list<array<string, mixed>> $rows PDO::fetchAll() is untyped, FETCH_ASSOC guarantees this shape */
            $rows = $this->pdoStatement->fetchAll(mode: PDO::FETCH_ASSOC);
            if (isset($dbQueryLogItem)) {
                $dbQueryLogItem->confirmFinishedExecution();
                DbQueryLogList::add($dbQueryLogItem);
            }
        } catch (Throwable $t) {
            throw new DbRuntimeException($t, $this->pdoStatement->queryString, $parameters);
        }

        return array_map(callback: static fn(array $row): DbRow => new DbRow(values: $row), array: $rows);
    }

    /**
     * @param list<mixed> $parameters
     *
     * @throws DbRowCountException If the query returns more than one row.
     */
    public function executeAndFetchRow(array $parameters): ?DbRow
    {
        $rows = $this->executeAndFetchRows(parameters: $parameters);
        if (count(value: $rows) > 1) {
            throw DbRowCountException::moreThanOneRow(
                rowCount: count(value: $rows),
                sql: $this->pdoStatement->queryString,
            );
        }

        return $rows[0] ?? null;
    }
}
