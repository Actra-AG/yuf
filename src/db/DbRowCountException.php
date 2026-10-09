<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

use UnexpectedValueException;

/**
 * A query that must return at most one row returned more.
 */
final class DbRowCountException extends UnexpectedValueException
{
    /**
     * For a query whose rows were not all fetched (the second row is enough to know it).
     */
    public static function moreThanOneRowWithoutCount(string $sql): DbRowCountException
    {
        return new DbRowCountException(
            message: 'Expected at most one row, but the query returned more than one row. SQL-String: "' . $sql . '"',
        );
    }

    public static function moreThanOneRow(int $rowCount, string $sql): DbRowCountException
    {
        return new DbRowCountException(
            message: 'Expected at most one row, but the query returned ' . $rowCount . ' rows.'
            . ' SQL-String: "' . $sql . '"',
        );
    }
}
