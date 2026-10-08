<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

use Closure;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * Executes a prepared statement, logs it if wanted and turns every failure into a `DbRuntimeException`.
 *
 * @internal
 * @phpstan-import-type SqlParameters from DbQueryData
 */
final readonly class DbStatementExecutor
{
    public function __construct(private ?DbQueryLogList $queryLog) {}

    /**
     * @template T
     * @param SqlParameters $parameters
     * @param Closure(PDOStatement): T $afterExecution Reads the result; its time counts to the execution time.
     *
     * @return T
     * @throws DbRuntimeException
     */
    public function run(PDOStatement $statement, array $parameters, Closure $afterExecution): mixed
    {
        try {
            $logItem = $this->queryLog?->start(sqlQuery: $statement->queryString, params: $parameters);
            if ($statement->execute(params: $parameters) === false) {
                throw new RuntimeException(message: 'PDOStatement->execute() returned false');
            }
            $result = $afterExecution($statement);
            if ($logItem !== null) {
                $logItem->confirmFinishedExecution();
                $this->queryLog->add(dbQueryLogItem: $logItem);
            }

            return $result;
        } catch (Throwable $throwable) {
            throw new DbRuntimeException(
                throwable: $throwable,
                sql: $statement->queryString,
                parameters: $parameters,
            );
        }
    }
}
