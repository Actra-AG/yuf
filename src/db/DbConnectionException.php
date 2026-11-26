<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

use PDOException;
use SensitiveParameter;
use Throwable;

/**
 * A connection that could not be opened. It carries the message and code of the original error but not the error
 * itself, whose stack trace holds the password.
 */
final class DbConnectionException extends PDOException
{
    /** "Unknown or incorrect time zone" of MySQL and MariaDB, e.g. in the init command. */
    private const int UNKNOWN_TIME_ZONE_ERROR = 1298;

    public static function fromThrowable(#[SensitiveParameter] Throwable $throwable): DbConnectionException
    {
        $message = $throwable->getMessage();
        if (DbConnectionException::isUnknownTimeZone(message: $message)) {
            $message = 'The database has no time zone tables: load them (mariadb-tzinfo-to-sql or '
                . 'mysql_tzinfo_to_sql) or set the time zone of DbSettings as an offset like "+01:00". ' . $message;
        }

        return new DbConnectionException($message, (int) $throwable->getCode());
    }

    /**
     * PDO puts the error number of the server into the message: "SQLSTATE[HY000] [1298] …" when connecting,
     * "SQLSTATE[HY000]: General error: 1298 …" after it.
     */
    private static function isUnknownTimeZone(string $message): bool
    {
        return preg_match(
            pattern: '/(?:\[|General error: )' . DbConnectionException::UNKNOWN_TIME_ZONE_ERROR . '\b/',
            subject: $message,
        ) === 1;
    }
}
