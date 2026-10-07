<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\renderer;

use actra\yuf\common\TimeOfDay;
use actra\yuf\form\component\field\DateField;
use actra\yuf\form\component\field\DecimalField;
use actra\yuf\form\component\field\FloatField;
use actra\yuf\form\component\field\HiddenIntegerField;
use actra\yuf\form\component\field\IntegerField;
use actra\yuf\form\component\field\NumericField;
use actra\yuf\form\component\field\TimeField;
use actra\yuf\form\FormInput;
use actra\yuf\html\HtmlText;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * The HTML of the number, date and time fields. The expected strings were rendered by yuf v3.3.1 (`AmountField`,
 * `NumericField`, `DateField`, `TimeField`, `HiddenField(valueIsInt: true)`) for the equivalent values.
 */
final class NumberAndDateFieldRenderersTest extends TestCase
{
    private function label(): HtmlText
    {
        return HtmlText::encoded(textContent: 'Label');
    }

    private function error(): HtmlText
    {
        return HtmlText::encoded(textContent: 'Invalid');
    }

    public function testEmptyIntegerField(): void
    {
        $field = new IntegerField(name: 'a', label: $this->label());

        $this->assertSame('<input type="text" name="a" id="a" value="">', $field->render());
    }

    public function testIntegerFieldWithValue(): void
    {
        $field = new IntegerField(name: 'a', label: $this->label(), initialValue: 5);

        $this->assertSame('<input type="text" name="a" id="a" value="5">', $field->render());
    }

    public function testIntegerFieldWithPostedValue(): void
    {
        $field = new IntegerField(name: 'a', label: $this->label());
        $field->validate(input: FormInput::fromArray(data: ['a' => ' 12 ']));

        $this->assertSame('<input type="text" name="a" id="a" value="12">', $field->render());
    }

    public function testIntegerFieldWithInvalidInput(): void
    {
        $field = new IntegerField(name: 'a', label: $this->label());
        $field->validate(input: FormInput::fromArray(data: ['a' => 'abc']));

        $this->assertSame(
            '<input type="text" name="a" id="a" value="abc" aria-invalid="true" aria-describedby="a-error">',
            $field->render(),
        );
    }

    public function testFloatFieldWithPlaceholderAndMaxLength(): void
    {
        $field = new FloatField(
            name: 'a',
            label: $this->label(),
            initialValue: 1.5,
            placeholder: 'ph',
            maxLength: 9,
        );

        $this->assertSame(
            '<input type="text" name="a" id="a" value="1.5" placeholder="ph" maxlength="9">',
            $field->render(),
        );
    }

    public function testFloatFieldWithWholeNumber(): void
    {
        $field = new FloatField(name: 'a', label: $this->label(), initialValue: 1.0);

        $this->assertSame('<input type="text" name="a" id="a" value="1">', $field->render());
    }

    public function testDecimalFieldRendersTheCanonicalValue(): void
    {
        $field = new DecimalField(name: 'a', label: $this->label(), scale: 2, initialValue: '12.5');

        $this->assertSame('<input type="text" name="a" id="a" value="12.50">', $field->render());
    }

    public function testNumericFieldWithMinAndMaxLength(): void
    {
        $field = new NumericField(name: 'n', label: $this->label(), initialValue: 7, minLength: 2, maxLength: 4);

        $this->assertSame(
            '<input type="text" name="n" id="n" value="7" maxlength="4" inputmode="numeric" pattern="\d{2,4}">',
            $field->render(),
        );
    }

    public function testNumericFieldWithoutMaxLength(): void
    {
        $field = new NumericField(name: 'n', label: $this->label(), minLength: 2);

        $this->assertSame(
            '<input type="text" name="n" id="n" value="" inputmode="numeric" pattern="\d{2,}">',
            $field->render(),
        );
    }

    public function testNumericFieldWithFixedLength(): void
    {
        $field = new NumericField(name: 'n', label: $this->label(), initialValue: 12, minLength: 2, maxLength: 2);

        $this->assertSame(
            '<input type="text" name="n" id="n" value="12" maxlength="2" inputmode="numeric" pattern="\d{2}">',
            $field->render(),
        );
    }

    public function testHiddenIntegerField(): void
    {
        $field = new HiddenIntegerField(name: 'id', value: 7);

        $this->assertSame('<input type="hidden" name="id" value="7">', $field->render());
    }

    public function testDateField(): void
    {
        $field = new DateField(
            name: 'd',
            label: $this->label(),
            value: new DateTimeImmutable(datetime: '2020-01-02'),
            invalidError: $this->error(),
        );

        $this->assertSame('<input type="date" name="d" id="d" value="2020-01-02">', $field->render());
    }

    public function testEmptyDateField(): void
    {
        $field = new DateField(name: 'd', label: $this->label(), value: null, invalidError: $this->error());

        $this->assertSame('<input type="date" name="d" id="d" value="">', $field->render());
    }

    public function testDateFieldWithSwissPostedDate(): void
    {
        $field = new DateField(name: 'd', label: $this->label(), value: null, invalidError: $this->error());
        $field->validate(input: FormInput::fromArray(data: ['d' => '3.2.2020']));

        $this->assertSame('<input type="date" name="d" id="d" value="2020-02-03">', $field->render());
    }

    public function testDateFieldWithInvalidInput(): void
    {
        $field = new DateField(name: 'd', label: $this->label(), value: null, invalidError: $this->error());
        $field->validate(input: FormInput::fromArray(data: ['d' => '2020-02-30']));

        $this->assertSame(
            '<input type="date" name="d" id="d" value="2020-02-30" aria-invalid="true" aria-describedby="d-error">',
            $field->render(),
        );
    }

    public function testTimeFieldWithPlaceholder(): void
    {
        $field = new TimeField(
            name: 't',
            label: $this->label(),
            value: new TimeOfDay(hour: 8, minute: 30),
            invalidError: $this->error(),
            placeholder: 'x',
        );

        $this->assertSame('<input type="time" name="t" id="t" value="08:30" placeholder="x">', $field->render());
    }

    public function testTimeFieldDropsTheSeconds(): void
    {
        $field = new TimeField(
            name: 't',
            label: $this->label(),
            value: new TimeOfDay(hour: 8, minute: 30, second: 15),
            invalidError: $this->error(),
        );

        $this->assertSame('<input type="time" name="t" id="t" value="08:30">', $field->render());
    }

    public function testTimeFieldWithInvalidInput(): void
    {
        $field = new TimeField(name: 't', label: $this->label(), value: null, invalidError: $this->error());
        $field->validate(input: FormInput::fromArray(data: ['t' => '25:00']));

        $this->assertSame(
            '<input type="time" name="t" id="t" value="25:00" aria-invalid="true" aria-describedby="t-error">',
            $field->render(),
        );
    }

    public function testTimeFieldWithRequiredError(): void
    {
        $field = new TimeField(
            name: 't',
            label: $this->label(),
            value: null,
            invalidError: $this->error(),
            requiredError: HtmlText::encoded(textContent: 'Req'),
        );
        $field->validate(input: FormInput::fromArray(data: ['t' => '']));

        $this->assertSame(
            '<input type="time" name="t" id="t" value="" aria-invalid="true" aria-describedby="t-error">',
            $field->render(),
        );
    }
}
