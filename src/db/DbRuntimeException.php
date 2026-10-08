<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

use PDOException;
use RuntimeException;
use Throwable;

/**
 * A database operation failed. Enriches the original `Throwable` (`getPrevious()`) with the SQL string and the number
 * of bound values, and carries the driver error code of a `PDOException` (e.g. 1062 for a duplicate entry of MySQL)
 * as `getCode()`.
 *
 * The bound values are not part of the message, because they may be personal data. The message of the driver itself
 * can contain values (e.g. the duplicate entry), so it is not meant for users.
 *
 * @phpstan-import-type SqlParameters from DbQueryData
 */
final class DbRuntimeException extends RuntimeException
{
    /**
     * @param SqlParameters $parameters Only their number is used.
     */
    public function __construct(Throwable $throwable, string $sql, array $parameters = [])
    {
        $parameterCount = count(value: $parameters);
        $message = $throwable->getMessage() . ';' . PHP_EOL
            . 'SQL-Parameters: ' . ($parameterCount === 0 ? 'none' : $parameterCount . ' bound values (not shown)')
            . PHP_EOL
            . 'SQL-String: "' . $sql . '"' . PHP_EOL;

        parent::__construct(
            message: $message,
            code: DbRuntimeException::codeOf(throwable: $throwable),
            previous: $throwable,
        );
    }

    private static function codeOf(Throwable $throwable): int
    {
        if (!$throwable instanceof PDOException) {
            $code = $throwable->getCode();

            return is_int(value: $code) ? $code : 0;
        }
        $errorInfo = $throwable->errorInfo;
        if ($errorInfo === null || !array_key_exists(key: 1, array: $errorInfo)) {
            return 0;
        }

        return is_int(value: $errorInfo[1]) ? $errorInfo[1] : 0;
    }
}
