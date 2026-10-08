<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\filter;

use actra\yuf\db\DbQueryData;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlText;
use DateTimeImmutable;
use LogicException;
use Override;

/**
 * Accepts the date formats of the input (`2026-03-01`, `2026-3-1`, `01.03.2026`, `1.3.2026`, each optionally with
 * `H:i` or `H:i:s`; the formats of the form `DateField`) and the render format of the field (the value it shows is
 * sent back when the filter form is submitted again). Everything else is invalid, e.g. relative dates (`tomorrow`, `+1 day`), an impossible date (`2026-02-30`)
 * or a date that does not look exactly like the format (`26-03-01`, `9:05`). Without a time, the time is the start of
 * the day for a field for dates from, otherwise the end of the day.
 */
final class DateFilterField extends AbstractTableFilterField
{
    private const string STORAGE_FORMAT = 'Y-m-d H:i:s';
    /** @var list<string> Formats of the input besides the render format */
    private const array INPUT_FORMATS = [
        'Y-m-d', 'Y-m-d H:i', 'Y-m-d H:i:s',
        'Y-n-j', 'Y-n-j H:i', 'Y-n-j H:i:s',
        'd.m.Y', 'd.m.Y H:i', 'd.m.Y H:i:s',
        'j.n.Y', 'j.n.Y H:i', 'j.n.Y H:i:s',
    ];

    private ?DateTimeImmutable $value = null;

    public function __construct(
        TableFilter $parentFilter,
        string $filterFieldIdentifier,
        HtmlText $label,
        private readonly string $dataTableColumnReference,
        private readonly bool $dateMustBeSameOrLater,
        private readonly string $renderFormat = 'd.m.Y H:i:s',
        bool $highlightFieldIfSelected = false,
    ) {
        parent::__construct(
            parentFilter: $parentFilter,
            filterFieldIdentifier: $filterFieldIdentifier,
            label: $label,
            highlightFieldIfSelected: $highlightFieldIfSelected,
        );
    }

    #[Override]
    public function init(): void
    {
        $valueFromSession = (string) $this->getFromSession(index: $this->identifier);
        if ($valueFromSession !== '') {
            $this->value = DateFilterField::parse(input: $valueFromSession, formats: [DateFilterField::STORAGE_FORMAT]);
        }
    }

    #[Override]
    public function checkInput(): void
    {
        $inputValue = (string) $this->httpRequest->getPostString(name: $this->identifier);
        if ($inputValue === '') {
            $this->reset();

            return;
        }
        $dateTimeObject = DateFilterField::parse(
            input: $inputValue,
            formats: [...DateFilterField::INPUT_FORMATS, $this->renderFormat],
        );
        if ($dateTimeObject === null) {
            $this->reset();

            return;
        }
        if (!str_contains(haystack: $inputValue, needle: ':')) {
            $dateTimeObject = $this->dateMustBeSameOrLater
                ? $dateTimeObject->setTime(hour: 0, minute: 0)
                : $dateTimeObject->setTime(hour: 23, minute: 59, second: 59);
        }
        $this->value = $dateTimeObject;
        $this->saveToSession(
            index: $this->identifier,
            value: $dateTimeObject->format(format: DateFilterField::STORAGE_FORMAT),
        );
    }

    /**
     * @param list<string> $formats
     *
     * @return ?DateTimeImmutable The first format the input has exactly, null if there is none
     */
    private static function parse(string $input, array $formats): ?DateTimeImmutable
    {
        foreach ($formats as $format) {
            $dateTime = DateTimeImmutable::createFromFormat(format: '!' . $format, datetime: $input);
            if (
                $dateTime !== false
                && DateTimeImmutable::getLastErrors() === false
                && $dateTime->format(format: $format) === $input
            ) {
                return $dateTime;
            }
        }

        return null;
    }

    #[Override]
    public function reset(): void
    {
        $this->value = null;
        $this->saveToSession(index: $this->identifier, value: '');
    }

    #[Override]
    public function getWhereCondition(): DbQueryData
    {
        if ($this->value === null) {
            throw new LogicException(
                message: 'The filter field ' . $this->identifier . ' has no date, so it has no condition: '
                . 'ask isSelected() first.',
            );
        }

        return new DbQueryData(
            query: $this->dataTableColumnReference . ($this->dateMustBeSameOrLater ? '>=' : '<=') . '?',
            params: [$this->value->format(format: DateFilterField::STORAGE_FORMAT)],
        );
    }

    #[Override]
    protected function renderField(): string
    {
        $classes = ['text'];
        if (
            $this->highlightFieldIfSelected
            && $this->isSelected()
        ) {
            $classes[] = 'highlight';
        }

        $identifier = HtmlEncoder::encode(value: $this->identifier);
        $value = $this->value === null ? '' : $this->value->format(format: $this->renderFormat);

        return '<input type="text" class="' . implode(separator: ' ', array: $classes) . '" name="' . $identifier
            . '" id="filter-' . $identifier . '" value="' . HtmlEncoder::encode(value: $value) . '">';
    }

    #[Override]
    public function isSelected(): bool
    {
        return $this->value !== null;
    }
}
