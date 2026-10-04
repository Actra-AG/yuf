<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\FormFieldValueMissingException;
use actra\yuf\form\settings\AutoCompleteEnum;
use actra\yuf\form\settings\InputTypeEnum;
use actra\yuf\html\HtmlText;
use DateTimeImmutable;
use UnexpectedValueException;

/**
 * A date (`?DateTimeImmutable` at 00:00:00, `null` if empty). Input: `Y-m-d` (`2020-02-03`, `2020-2-3`) or the Swiss
 * format `d.m.Y` (`3.2.2020`); impossible dates (`2020-02-30`) are invalid. Input that cannot be parsed is kept for
 * re-rendering and gives `invalidError`. The field renders `Y-m-d`. Only the date part of a given value is used.
 */
final class DateField extends ParsedInputField
{
    private const string FORMAT = 'Y-m-d';

    private ?DateTimeImmutable $value = null;

    public function __construct(
        string $name,
        HtmlText $label,
        ?DateTimeImmutable $value,
        HtmlText $invalidError,
        ?HtmlText $requiredError = null,
        ?string $placeholder = null,
        ?AutoCompleteEnum $autoComplete = null
    ) {
        parent::__construct(
            inputType: InputTypeEnum::DATE,
            name: $name,
            label: $label,
            invalidError: $invalidError,
            requiredError: $requiredError,
            placeholder: $placeholder,
            autoComplete: $autoComplete
        );
        if ($value !== null) {
            $this->changeInitialText(text: $value->format(format: DateField::FORMAT));
        }
    }

    protected function accept(string $text): void
    {
        $this->value = DateField::parse(text: $text);
        parent::accept(text: $this->value?->format(format: DateField::FORMAT) ?? $text);
    }

    private static function parse(string $text): ?DateTimeImmutable
    {
        if (preg_match(pattern: '/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/D', subject: $text, matches: $matches) === 1) {
            [, $day, $month, $year] = $matches;
        } elseif (preg_match(pattern: '/^(\d{4})-(\d{1,2})-(\d{1,2})$/D', subject: $text, matches: $matches) === 1) {
            [, $year, $month, $day] = $matches;
        } else {
            return null;
        }
        if (!checkdate(month: (int)$month, day: (int)$day, year: (int)$year)) {
            return null;
        }

        return new DateTimeImmutable(datetime: sprintf('%04d-%02d-%02d', (int)$year, (int)$month, (int)$day));
    }

    protected function hasParsedValue(): bool
    {
        return $this->value !== null;
    }

    /**
     * Returns the date at 00:00:00, or `null` if the field is empty.
     *
     * @throws UnexpectedValueException If the field holds input that is not a date (before validation or after a
     *         failed validation).
     */
    public function getValueAsDateTimeImmutable(): ?DateTimeImmutable
    {
        $this->assertValueCanBeRead(type: 'date');

        return $this->value;
    }

    /**
     * Returns the value of a required field. Use it after a successful `validate()`, the nullable getter for an
     * optional field.
     *
     * @throws FormFieldValueMissingException If the field is empty (not validated yet, or not required).
     * @throws UnexpectedValueException If the field holds input that is not valid.
     */
    public function getRequiredValueAsDateTimeImmutable(): DateTimeImmutable
    {
        return $this->getValueAsDateTimeImmutable()
            ?? throw $this->valueMissing(nullableGetter: 'getValueAsDateTimeImmutable');
    }

    /**
     * Changes the current value only, the initial value stays (so `valueHasChanged()` compares with it).
     *
     */
    public function setValue(?DateTimeImmutable $value): void
    {
        $this->changeText(text: $value?->format(format: DateField::FORMAT) ?? '');
    }
}