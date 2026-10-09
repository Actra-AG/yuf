<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

use Generator;
use PDO;
use PDOStatement;
use stdClass;
use Throwable;

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
        return $this->executor->run(
            statement: $this->pdoStatement,
            parameters: $parameters,
            afterExecution: static function (PDOStatement $statement): array {
                $rows = [];
                // Row by row: no second, untyped copy of the whole result
                while (is_array(value: $values = $statement->fetch(mode: PDO::FETCH_ASSOC))) {
                    /** @var array<string, mixed> $values FETCH_ASSOC gives this shape */
                    $rows[] = new DbRow(values: $values);
                }

                return $rows;
            },
        );
    }

    /**
     * Executes the statement and returns its typed rows one by one, so a large result (an export, a cron job) is never
     * held in PHP at once. Read the rows before the next query on the same statement. MySQL buffers the result of a
     * query on the client by default; for very large results disable that for the connection
     * (`Pdo\Mysql::ATTR_USE_BUFFERED_QUERY`), then no other query may run until all rows are read.
     *
     * @param SqlParameters $parameters
     *
     * @return Generator<int, DbRow>
     * @throws DbRuntimeException if the query fails (on the call) or a row cannot be read (while iterating)
     */
    public function executeAndIterate(array $parameters): Generator
    {
        $this->executor->run(
            statement: $this->pdoStatement,
            parameters: $parameters,
            afterExecution: static fn(PDOStatement $statement): null => null,
        );

        return $this->iterateRows(parameters: $parameters);
    }

    /**
     * @param SqlParameters $parameters
     *
     * @return Generator<int, DbRow>
     * @throws DbRuntimeException
     */
    private function iterateRows(array $parameters): Generator
    {
        while (true) {
            try {
                $values = $this->pdoStatement->fetch(mode: PDO::FETCH_ASSOC);
            } catch (Throwable $throwable) {
                throw new DbRuntimeException(
                    throwable: $throwable,
                    sql: $this->pdoStatement->queryString,
                    parameters: $parameters,
                );
            }
            if (!is_array(value: $values)) {
                return;
            }
            /** @var array<string, mixed> $values FETCH_ASSOC gives this shape */
            yield new DbRow(values: $values);
        }
    }

    /**
     * Fetches at most two rows: the second one is enough to know that there is more than one.
     *
     * @param SqlParameters $parameters
     *
     * @return DbRow|null null if the query returns no row
     * @throws DbRuntimeException
     * @throws DbRowCountException If the query returns more than one row.
     */
    public function executeAndFetchRow(array $parameters): ?DbRow
    {
        $rows = $this->executor->run(
            statement: $this->pdoStatement,
            parameters: $parameters,
            afterExecution: static function (PDOStatement $statement): array {
                $rows = [];
                while (
                    count(value: $rows) < 2
                    && is_array(value: $values = $statement->fetch(mode: PDO::FETCH_ASSOC))
                ) {
                    /** @var array<string, mixed> $values FETCH_ASSOC gives this shape */
                    $rows[] = new DbRow(values: $values);
                }
                $statement->closeCursor();

                return $rows;
            },
        );
        if (count(value: $rows) > 1) {
            throw DbRowCountException::moreThanOneRowWithoutCount(sql: $this->pdoStatement->queryString);
        }

        return array_first(array: $rows);
    }
}
