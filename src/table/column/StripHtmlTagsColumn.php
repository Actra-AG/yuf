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

class StripHtmlTagsColumn extends AbstractTableColumn
{
    #[Override]
    protected function renderCellValue(TableItem $tableItem): string
    {
        $strippedTags = strip_tags(string: $tableItem->getRawValue(name: $this->identifier));

        return HtmlEncoder::encodeKeepQuotes(value: $strippedTags);
    }
}
