<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use actra\yuf\html\HtmlText;
use actra\yuf\table\TableItem;
use DateTimeImmutable;
use Override;

class DateColumn extends AbstractTableColumn
{
    public string $format = 'd.m.Y H:i:s';
    private ?HtmlText $emptyValueText = null;

    public function setEmptyValueText(HtmlText $htmlText): void
    {
        $this->emptyValueText = $htmlText;
    }

    #[Override]
    protected function renderCellValue(TableItem $tableItem): string
    {
        $value = trim(string: (string) $tableItem->getRawValue(name: $this->identifier));

        if ($value === '') {
            return $this->emptyValueText === null ? '' : $this->emptyValueText->render();
        }

        return new DateTimeImmutable(datetime: $value)->format(format: $this->format);
    }
}
