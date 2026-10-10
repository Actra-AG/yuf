<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use actra\yuf\core\Language;
use actra\yuf\html\HtmlText;
use actra\yuf\table\TableItem;
use DateMalformedStringException;
use DateTimeImmutable;
use IntlDateFormatter;
use InvalidArgumentException;
use Override;
use UnexpectedValueException;

/**
 * A date or date and time of the database. The fixed format `$format` (`d.m.Y H:i:s`) is the default;
 * `useLocale()` writes it the way the language of the request does (`IntlDateFormatter`).
 */
final class DateColumn extends AbstractTableColumn
{
    public string $format = 'd.m.Y H:i:s';
    private ?HtmlText $emptyValueText = null;
    private ?IntlDateFormatter $localeFormatter = null;

    public function setEmptyValueText(HtmlText $htmlText): void
    {
        $this->emptyValueText = $htmlText;
    }

    /**
     * Writes the dates in the style of the locale of the language (e.g. `de_CH`: `05.10.2026, 08:30`), instead of with
     * `$format`; the date is written in the time zone it was parsed in. Texts like month names are the ones of ICU, so
     * they follow the ICU version of the server.
     *
     * @param DateStyleEnum $timeStyle `NONE` for the date only
     *
     * @throws InvalidArgumentException If both styles are `NONE` or ICU does not know the locale
     */
    public function useLocale(
        Language $language,
        DateStyleEnum $dateStyle = DateStyleEnum::MEDIUM,
        DateStyleEnum $timeStyle = DateStyleEnum::SHORT,
    ): void {
        if ($dateStyle === DateStyleEnum::NONE && $timeStyle === DateStyleEnum::NONE) {
            throw new InvalidArgumentException(message: 'The date style and the time style must not both be NONE.');
        }
        $formatter = IntlDateFormatter::create(
            locale: $language->locale,
            dateType: $dateStyle->value,
            timeType: $timeStyle->value,
        );
        if ($formatter === null) {
            throw new InvalidArgumentException(
                message: 'The locale "' . $language->locale . '" of the language "' . $language->code
                . '" cannot be used to format dates: ' . intl_get_error_message(),
            );
        }
        $this->localeFormatter = $formatter;
    }

    #[Override]
    protected function renderCellValue(TableItem $tableItem): string
    {
        $value = trim(string: (string) $tableItem->getRawValue(name: $this->identifier));

        if ($value === '') {
            return $this->emptyValueText === null ? '' : $this->emptyValueText->render();
        }

        try {
            $date = new DateTimeImmutable(datetime: $value);
        } catch (DateMalformedStringException $exception) {
            throw new UnexpectedValueException(
                message: 'Column "' . $this->identifier . '" does not hold a date: ' . $exception->getMessage(),
                previous: $exception,
            );
        }

        if ($this->localeFormatter === null) {
            return $date->format(format: $this->format);
        }

        return $this->formatLocalized(formatter: $this->localeFormatter, date: $date);
    }

    private function formatLocalized(IntlDateFormatter $formatter, DateTimeImmutable $date): string
    {
        // The formatter would use the default time zone, which differs from the one the value was parsed in
        $formatter->setTimeZone(timezone: $date->getTimezone());

        $formatted = $formatter->format(datetime: $date);
        if ($formatted === false) {
            throw new UnexpectedValueException(
                message: 'Column "' . $this->identifier . '" cannot be formatted: ' . $formatter->getErrorMessage(),
            );
        }

        return $formatted;
    }
}
