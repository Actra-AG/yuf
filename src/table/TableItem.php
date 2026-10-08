<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table;

use actra\yuf\db\DbRow;
use actra\yuf\html\HtmlEncoder;
use InvalidArgumentException;
use stdClass;
use UnexpectedValueException;

/**
 * One row of a table: the values by column name, as fetched from the database or given by the application.
 */
final readonly class TableItem
{
    /** @var array<string, mixed> The values of any data source (a row of the database holds scalars and null) */
    public array $data;

    public function __construct(stdClass $dataObject)
    {
        /** @var array<string, mixed> $data Property names of a fetched row are its column names */
        $data = get_object_vars(object: $dataObject);
        $this->data = $data;
    }

    /**
     * Typed access to the values of this row, e.g. `$tableItem->getRow()->getInt(column: 'ID')`.
     */
    public function getRow(): DbRow
    {
        return new DbRow(values: $this->data);
    }

    /**
     * Untyped value as fetched from the database. Prefer the typed getters of `getRow()`.
     *
     * @throws InvalidArgumentException If the row has no such column
     */
    public function getRawValue(string $name): mixed
    {
        if (!array_key_exists(key: $name, array: $this->data)) {
            throw new InvalidArgumentException(
                message: 'The row has no column "' . $name . '", it has: ' . implode(
                    separator: ', ',
                    array: array_keys(array: $this->data),
                ) . '.',
            );
        }

        return $this->data[$name];
    }

    /**
     * The value of a column that holds a scalar or NULL, as the database delivers them.
     *
     * @throws UnexpectedValueException If the value is an array or an object
     */
    public function getScalarValue(string $name): bool|float|int|string|null
    {
        $value = $this->getRawValue(name: $name);
        if ($value === null || is_scalar(value: $value)) {
            return $value;
        }

        throw new UnexpectedValueException(
            message: 'Column "' . $name . '" holds a ' . get_debug_type(value: $value)
            . ', which cannot be rendered. Use a CallbackColumn to render it.',
        );
    }

    /**
     * The value as HTML text: encoded, NULL is empty.
     */
    public function renderValue(string $name, bool $renderNewLines = false): string
    {
        $value = $this->getScalarValue(name: $name);
        if ($value === null) {
            return '';
        }

        if ($renderNewLines) {
            return nl2br(
                string: HtmlEncoder::encodeKeepQuotes(
                    value: str_replace(
                        search: '<br>',
                        replace: PHP_EOL,
                        subject: (string) $value,
                    ),
                ),
            );
        }

        return HtmlEncoder::encodeKeepQuotes(value: $value);
    }
}
