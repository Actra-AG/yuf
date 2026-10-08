<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

use PDO;
use PDOStatement;
use stdClass;

/**
 * A prepared SELECT statement that can be executed repeatedly. Created by `FrameworkDb::prepareSelect()`.
 *
 * Mimics the effect of `FrameworkDb::select()`: execute and fetch in ONE call.
 *
 * @phpstan-import-type SqlParameters from DbQueryData
 */
final readonly class DbSelectStmt
{
    private DbStatementExecutor $executor;

    /**
     * @param ?DbQueryLogList $queryLog Executions are logged there if given.
     */
    public function __construct(private PDOStatement $pdoStatement, ?DbQueryLogList $queryLog = null)
    {
        $this->executor = new DbStatementExecutor(queryLog: $queryLog);
    }

    /**
     * @param SqlParameters $parameters
     *
     * @return list<stdClass>
     * @throws DbRuntimeException
     */
    public function executeAndFetch(array $parameters): array
    {
        return $this->executor->run(
            statement: $this->pdoStatement,
            parameters: $parameters,
            afterExecution: static function (PDOStatement $statement): array {
                /** @var list<stdClass> $rows PDO::fetchAll() is untyped, FETCH_OBJ guarantees this shape */
                $rows = $statement->fetchAll(mode: PDO::FETCH_OBJ);

                return $rows;
            },
        );
    }

    /**
     * Like executeAndFetch(), but returns typed rows.
     *
     * @param SqlParameters $parameters
     *
     * @return list<DbRow>
     * @throws DbRuntimeException
     */
    public function executeAndFetchRows(array $parameters): array
    {
        $rows = $this->executor->run(
            statement: $this->pdoStatement,
            parameters: $parameters,
            afterExecution: static function (PDOStatement $statement): array {
                /** @var list<array<string, mixed>> $rows PDO::fetchAll() is untyped, FETCH_ASSOC gives this shape */
                $rows = $statement->fetchAll(mode: PDO::FETCH_ASSOC);

                return $rows;
            },
        );

        return array_map(callback: static fn(array $row): DbRow => new DbRow(values: $row), array: $rows);
    }

    /**
     * @param SqlParameters $parameters
     *
     * @return DbRow|null null if the query returns no row
     * @throws DbRuntimeException
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

        return array_first(array: $rows);
    }
}
