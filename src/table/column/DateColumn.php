<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use actra\yuf\html\HtmlText;
use actra\yuf\table\TableItem;
use DateMalformedStringException;
use DateTimeImmutable;
use Override;
use UnexpectedValueException;

final class DateColumn extends AbstractTableColumn
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
        $value = trim(string: (string) $tableItem->getScalarValue(name: $this->identifier));

        if ($value === '') {
            return $this->emptyValueText === null ? '' : $this->emptyValueText->render();
        }

        try {
            return new DateTimeImmutable(datetime: $value)->format(format: $this->format);
        } catch (DateMalformedStringException $exception) {
            throw new UnexpectedValueException(
                message: 'Column "' . $this->identifier . '" does not hold a date: ' . $exception->getMessage(),
                previous: $exception,
            );
        }
    }
}
