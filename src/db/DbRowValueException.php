<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

use UnexpectedValueException;

/**
 * A column of a fetched row is missing or holds a value that does not match the requested type.
 */
final class DbRowValueException extends UnexpectedValueException
{
    public static function missingColumn(string $column): DbRowValueException
    {
        return new DbRowValueException(message: 'Column "' . $column . '" does not exist in the row.');
    }

    public static function unexpectedNull(string $column, string $expectedType): DbRowValueException
    {
        return new DbRowValueException(
            message: 'Column "' . $column . '" is NULL, but expected ' . $expectedType
            . '. Use the nullable getter if NULL is allowed.',
        );
    }

    public static function wrongType(string $column, string $expectedType, string $actualType): DbRowValueException
    {
        return new DbRowValueException(
            message: 'Column "' . $column . '" has the type ' . $actualType . ', but expected ' . $expectedType . '.',
        );
    }

    /**
     * For values of the right PHP type but with a content that is not valid (e.g. `'abc'` as integer).
     */
    public static function invalidValue(string $column, string $expectedType, string $reason): DbRowValueException
    {
        return new DbRowValueException(
            message: 'Column "' . $column . '" does not hold a valid ' . $expectedType . ': ' . $reason . '.',
        );
    }
}
