<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

use InvalidArgumentException;
use LogicException;
use Override;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use stdClass;
use Throwable;
use UnexpectedValueException;

/**
 * A PDO connection that throws on every error, binds values only, can log queries and returns typed rows.
 *
 * Extension point: projects extend it to add their own query methods and hold the connection (e.g. a `DB::get()`
 * accessor). The class holds no static state: one instance is one connection, created with
 * `new FrameworkDb(connectionParameters: DbConnectionParameters::forMysql(dbSettings: $dbSettings))`.
 *
 * All SQL must use `?` placeholders for values (see `standards/security.md`): values are never part of the SQL string.
 *
 * @phpstan-import-type SqlParameters from DbQueryData
 */
class FrameworkDb extends PDO
{
    /** The attributes `FrameworkDb` relies on: they override the options of the connection parameters. */
    private const array FIXED_ATTRIBUTES = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ];

    private bool $usedTransactions = false;
    private readonly DbQueryLogList $queryLog;

    /**
     * @throws PDOException If the connection cannot be opened. The exception does not carry the password.
     */
    public function __construct(
        DbConnectionParameters $connectionParameters,
        DbQueryLogList $queryLog = new DbQueryLogList(),
    ) {
        $this->queryLog = $queryLog;
        try {
            parent::__construct(
                dsn: $connectionParameters->dsn,
                username: $connectionParameters->userName,
                password: $connectionParameters->password,
                options: array_replace($connectionParameters->options, FrameworkDb::FIXED_ATTRIBUTES),
            );
        } catch (Throwable $throwable) {
            // We do not want to leak the database password in the StackTrace of the caught (PDO)Exception.
            throw new PDOException(
                $throwable->getMessage(),
                (int) $throwable->getCode(),
            );
        }
    }

    /**
     * Prepares a SELECT statement for repeated execution and returns a special statement object.
     *
     * @param string $query Valid SQL statement
     * @param array<int, bool|int|string> $options Attributes of the returned PDOStatement
     *
     * @throws DbRuntimeException
     */
    public function prepareSelect(string $query, array $options = [], bool $logQuery = true): DbSelectStmt
    {
        return new DbSelectStmt(
            pdoStatement: $this->prepare(query: $query, options: $options),
            queryLog: $logQuery ? $this->queryLog : null,
        );
    }

    /**
     * Prepares a statement for execution and returns a statement object.
     *
     * @param string $query Valid SQL statement
     * @param array<mixed> $options Attributes of the returned PDOStatement
     *
     * @throws DbRuntimeException
     */
    #[Override]
    public function prepare(string $query, array $options = []): PDOStatement
    {
        try {
            $statement = parent::prepare(query: $query, options: $options);
        } catch (PDOException $pdoException) {
            throw new DbRuntimeException(throwable: $pdoException, sql: $query);
        }
        if ($statement === false) {
            throw new DbRuntimeException(
                throwable: new RuntimeException(message: 'Could not prepare query.'),
                sql: $query,
            );
        }

        return $statement;
    }

    /**
     * This method is a shorthand to select data from the database:
     * "(prepare($sql)->execute($parameters))->fetchAll(PDO::FETCH_OBJ)"
     *
     * @param string $sql valid SQL statement
     * @param SqlParameters $parameters list of parameter values to bind to the prepared sql statement in correct order
     *
     * @return list<stdClass> Each row as an object of type stdClass
     * @throws DbRuntimeException
     */
    public function select(string $sql, array $parameters = [], bool $logQuery = false): array
    {
        return $this->prepareSelect(query: $sql, logQuery: $logQuery)->executeAndFetch(parameters: $parameters);
    }

    /**
     * Like select(), but returns typed rows (see DbRow) instead of untyped stdClass objects.
     *
     * @param SqlParameters $parameters list of parameter values to bind to the prepared sql statement in correct order
     *
     * @return list<DbRow>
     * @throws DbRuntimeException
     */
    public function selectRows(string $sql, array $parameters = [], bool $logQuery = false): array
    {
        return $this->prepareSelect(query: $sql, logQuery: $logQuery)->executeAndFetchRows(parameters: $parameters);
    }

    /**
     * Selects at most one typed row.
     *
     * @param SqlParameters $parameters list of parameter values to bind to the prepared sql statement in correct order
     *
     * @return DbRow|null null if the query returns no row
     * @throws DbRuntimeException
     * @throws DbRowCountException If the query returns more than one row.
     */
    public function selectRow(string $sql, array $parameters = [], bool $logQuery = false): ?DbRow
    {
        return $this->prepareSelect(query: $sql, logQuery: $logQuery)->executeAndFetchRow(parameters: $parameters);
    }

    /**
     * This method is a shorthand for "(prepare($sql))->execute($parameters)"
     *
     * @param string $sql valid SQL statement
     * @param SqlParameters $parameters list of parameter values to bind to the prepared sql statement in correct order
     *
     * @return PDOStatement The prepared statement after execution
     * @throws DbRuntimeException
     */
    public function execute(string $sql, array $parameters = [], bool $logQuery = false): PDOStatement
    {
        return new DbStatementExecutor(queryLog: $logQuery ? $this->queryLog : null)->run(
            statement: $this->prepare(query: $sql),
            parameters: $parameters,
            afterExecution: static fn(PDOStatement $statement): PDOStatement => $statement,
        );
    }

    public function __destruct()
    {
        if ($this->usedTransactions && $this->inTransaction()) {
            // That error is hard to detect, because in that case PHP silently (!) does a rollback!
            throw new LogicException(message: 'An active transaction was not closed properly! Data changes are lost!');
        }
    }

    /**
     * Begins a transaction.
     *
     * @return bool Always returns true, otherwise an Exception because of the severity of the failure
     * @throws LogicException
     * @throws RuntimeException
     */
    #[Override]
    public function beginTransaction(): bool
    {
        // Some drivers are mocking about the transaction. We can't tolerate that!
        if ($this->inTransaction()) {
            throw new LogicException(message: 'A transaction is already active. Check program code.');
        }
        $hasStarted = parent::beginTransaction();
        if (!$hasStarted || !$this->inTransaction()) {
            throw new RuntimeException(
                message: 'Could not start transaction. Either error in the underlying driver,'
                . ' or check table engine declaration.',
            );
        }
        $this->usedTransactions = true;

        return true;
    }

    /**
     * Make changes within a transaction permanent.
     *
     * @return bool Always returns true, otherwise an Exception because of the severity of the failure
     * @throws LogicException
     * @throws RuntimeException
     */
    #[Override]
    public function commit(): bool
    {
        if (!$this->inTransaction()) {
            throw new LogicException(message: 'There was no active transaction! Check program code.');
        }
        if (!parent::commit()) {
            throw new RuntimeException(message: 'Could not commit transaction! Withhold data changes are lost!');
        }

        return true;
    }

    /**
     * Drops any action within a transaction, thus altering no data.
     *
     * @return bool Always returns true, otherwise an Exception because of the severity of the failure
     * @throws LogicException
     * @throws RuntimeException
     */
    #[Override]
    public function rollBack(): bool
    {
        if (!$this->inTransaction()) {
            throw new LogicException(message: 'There was no active transaction! Check program code.');
        }
        if (!parent::rollBack()) {
            throw new RuntimeException(message: 'Could not rollback transaction! Done data changes are permanent!');
        }

        return true;
    }

    /**
     * Creates a string like "?,?,?,..." with one placeholder per given value, for "WHERE x IN (...)".
     *
     * @param array<array-key, float|int|string|null> $values
     *
     * @throws InvalidArgumentException If there is no value: "IN ()" is no valid SQL.
     */
    public function createInQuery(array $values): string
    {
        if ($values === []) {
            throw new InvalidArgumentException(message: 'An IN list needs at least one value.');
        }

        return implode(separator: ',', array: array_fill(start_index: 0, count: count(value: $values), value: '?'));
    }

    /**
     * @return list<DbQueryLogItem>
     */
    public function getQueryLog(): array
    {
        return $this->queryLog->getItems();
    }

    /**
     * The ID that the last INSERT of this connection generated (0 if there was none). Replaces the override of
     * `PDO::lastInsertId()`, which changed the return type of the parent.
     *
     * @throws UnexpectedValueException If the driver does not return an integer ID.
     */
    public function getLastInsertId(): int
    {
        $lastInsertId = parent::lastInsertId();
        if ($lastInsertId === false || preg_match(pattern: '/^\d{1,18}$/D', subject: $lastInsertId) !== 1) {
            throw new UnexpectedValueException(message: 'The last insert ID is not an integer.');
        }

        return (int) $lastInsertId;
    }

    /**
     * Not available: use `getLastInsertId(): int`. yuf's former override returned `int`; failing loudly keeps old calls
     * from silently getting the `string` of PDO.
     *
     * @throws LogicException Always
     */
    #[Override]
    public function lastInsertId(?string $name = null): string|false
    {
        throw new LogicException(message: 'Use FrameworkDb::getLastInsertId(): int instead of lastInsertId().');
    }
}
