<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use actra\yuf\html\HtmlEncoder;
use actra\yuf\table\TableItemModel;
use Override;

class StripHtmlTagsColumn extends AbstractTableColumn
{
    #[Override]
    protected function renderCellValue(TableItemModel $tableItemModel): string
    {
        $strippedTags = strip_tags(string: $tableItemModel->getRawValue(name: $this->identifier));

        return HtmlEncoder::encodeKeepQuotes(value: $strippedTags);
    }
}
