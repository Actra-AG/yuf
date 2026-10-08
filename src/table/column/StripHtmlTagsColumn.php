<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use actra\yuf\html\HtmlEncoder;
use actra\yuf\table\TableItem;
use Override;

/**
 * The text of a value that holds HTML: tags removed, the rest encoded.
 */
final class StripHtmlTagsColumn extends AbstractTableColumn
{
    #[Override]
    protected function renderCellValue(TableItem $tableItem): string
    {
        $value = $tableItem->getScalarValue(name: $this->identifier);
        if ($value === null) {
            return '';
        }

        return HtmlEncoder::encodeKeepQuotes(value: strip_tags(string: (string) $value));
    }
}
