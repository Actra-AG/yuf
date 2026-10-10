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
 * One row of a table: the values by column name, as fetched from the database or given by the application. A value is
 * a scalar or `null` (what a database row holds); this keeps every cell renderable and exportable (CSV).
 */
final readonly class TableItem
{
    /** @var array<string, bool|float|int|string|null> */
    public array $data;

    /**
     * @throws UnexpectedValueException If a property is an array or an object
     */
    public function __construct(stdClass $dataObject)
    {
        $data = [];
        foreach (get_object_vars(object: $dataObject) as $name => $value) {
            if ($value !== null && !is_scalar(value: $value)) {
                throw new UnexpectedValueException(
                    message: 'Column "' . $name . '" holds a ' . get_debug_type(value: $value)
                    . ', but a table row holds scalars and NULL only. Render other values with a CallbackColumn.',
                );
            }
            $data[(string) $name] = $value;
        }
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
     * The value of a column as the database delivers it (a scalar or NULL). Prefer the typed getters of `getRow()`.
     *
     * @throws InvalidArgumentException If the row has no such column
     */
    public function getRawValue(string $name): bool|float|int|string|null
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
     * The value as HTML text: encoded, NULL is empty.
     */
    public function renderValue(string $name, bool $renderNewLines = false): string
    {
        $value = $this->getRawValue(name: $name);
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
