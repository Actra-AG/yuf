<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table;

use actra\yuf\db\DbRow;
use actra\yuf\html\HtmlEncoder;
use stdClass;
use UnexpectedValueException;

readonly class TableItemModel
{
    /** @var array<string, mixed> */
    public array $data;

    public function __construct(stdClass $dataObject)
    {
        /** @var array<string, mixed> $data Property names of a fetched row are its column names */
        $data = get_object_vars(object: $dataObject);
        $this->data = $data;
    }

    /**
     * Typed access to the values of this row, e.g. `$tableItemModel->getRow()->getInt(column: 'ID')`.
     */
    public function getRow(): DbRow
    {
        return new DbRow(values: $this->data);
    }

    /**
     * Untyped value as fetched from the database. Prefer the typed getters of `getRow()`.
     */
    public function getRawValue(string $name): mixed
    {
        return $this->data[$name];
    }

    public function renderValue(string $name, bool $renderNewLines = false): string
    {
        $value = $this->data[$name];
        if ($value === null) {
            return '';
        }
        if (!is_scalar(value: $value)) {
            throw new UnexpectedValueException(
                message: 'Column "' . $name . '" holds a ' . get_debug_type(value: $value)
                . ', which cannot be rendered. Use a CallbackColumn to render it.'
            );
        }

        if ($renderNewLines) {
            return nl2br(
                string: HtmlEncoder::encodeKeepQuotes(
                    value: str_replace(
                        search: '<br>',
                        replace: PHP_EOL,
                        subject: (string)$value
                    )
                )
            );
        }

        return HtmlEncoder::encodeKeepQuotes(value: $value);
    }
}