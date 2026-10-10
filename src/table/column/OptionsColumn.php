<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlText;
use actra\yuf\table\TableItem;
use Override;

/**
 * Shows the label of the option that has the value of the column as key, or the value itself if there is none.
 */
final class OptionsColumn extends AbstractTableColumn
{
    /**
     * @param array<int|string, HtmlText|string> $options Value of the column => label; a string is text and encoded,
     *                                                    HTML of the application is given as `HtmlText::fromHtml()`
     */
    public function __construct(
        string $identifier,
        string $label,
        private readonly array $options,
        bool $isSortable,
        bool $sortAscendingByDefault = true,
    ) {
        parent::__construct(
            identifier: $identifier,
            label: $label,
            isSortable: $isSortable,
            sortAscendingByDefault: $sortAscendingByDefault,
        );
    }

    #[Override]
    protected function renderCellValue(TableItem $tableItem): string
    {
        $value = $tableItem->getRawValue(name: $this->identifier);
        if (
            (is_int(value: $value) || is_string(value: $value))
            && array_key_exists(key: $value, array: $this->options)
        ) {
            $option = $this->options[$value];

            return $option instanceof HtmlText ? $option->render() : HtmlEncoder::encodeKeepQuotes(value: $option);
        }

        return $tableItem->renderValue(name: $this->identifier);
    }
}
