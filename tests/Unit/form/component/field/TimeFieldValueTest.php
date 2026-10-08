<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\common\TimeOfDay;
use actra\yuf\form\component\field\TimeField;
use actra\yuf\form\FormInput;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use UnexpectedValueException;

/**
 * TimeField holds a `?TimeOfDay`.
 */
final class TimeFieldValueTest extends TestCase
{
    private function createField(?TimeOfDay $value = null, ?HtmlText $requiredError = null): TimeField
    {
        return new TimeField(
            name: 'time',
            label: HtmlText::fromHtml(html: 'Time'),
            value: $value,
            invalidError: HtmlText::fromHtml(html: 'Invalid'),
            requiredError: $requiredError,
        );
    }

    public function testEmptyFieldHasNoValue(): void
    {
        $field = $this->createField();

        $this->assertNull($field->getValueAsTimeOfDay());
        $this->assertTrue($field->isValueEmpty());
    }

    public function testInitialValueIsReturnedAndRenderedWithoutSeconds(): void
    {
        $field = $this->createField(value: new TimeOfDay(hour: 8, minute: 30, second: 15));

        $this->assertTrue(
            $field->getValueAsTimeOfDay()?->equals(other: new TimeOfDay(hour: 8, minute: 30, second: 15)) === true,
        );
        $this->assertStringContainsString('value="08:30"', $field->render());
        $this->assertFalse($field->valueHasChanged());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validInputProvider(): iterable
    {
        yield 'hours and minutes' => ['08:05', '08:05:00'];
        yield 'with seconds' => ['08:05:07', '08:05:07'];
        yield 'midnight' => ['00:00', '00:00:00'];
        yield 'last second of the day' => ['23:59:59', '23:59:59'];
        yield 'surrounding whitespace' => [' 08:05 ', '08:05:00'];
    }

    #[DataProvider('validInputProvider')]
    public function testValidTimeIsParsed(string $input, string $expected): void
    {
        $field = $this->createField();

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['time' => $input])));
        $this->assertSame($expected, $field->getValueAsTimeOfDay()?->toString());
        $shortText = substr(string: $expected, offset: 0, length: 5);

        $this->assertStringContainsString('value="' . $shortText . '"', $field->render());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidInputProvider(): iterable
    {
        yield 'hour out of range' => ['25:00'];
        yield 'minute out of range' => ['08:60'];
        yield 'second out of range' => ['08:00:60'];
        yield 'single digit hour' => ['8:30'];
        yield 'no colon' => ['0830'];
        yield 'text' => ['noon'];
        yield 'with date' => ['2020-01-02 08:00'];
        yield 'trailing colon' => ['08:30:'];
        yield 'am pm' => ['08:30 pm'];
    }

    #[DataProvider('invalidInputProvider')]
    public function testInvalidTimeIsAnErrorAndKeptForRendering(string $input): void
    {
        $field = $this->createField();

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['time' => $input])));
        $this->assertSame('Invalid', $field->errorCollection->getFirstError()->render());
        $this->assertSame(1, $field->errorCollection->count());
        $this->assertStringContainsString('value="' . $input . '"', $field->render());
    }

    #[DataProvider('invalidInputProvider')]
    public function testGetterThrowsForInvalidTime(string $input): void
    {
        $field = $this->createField();
        $field->validate(input: FormInput::fromArray(data: ['time' => $input]));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('field time');

        $field->getValueAsTimeOfDay();
    }

    public function testEmptyInputIsValidWithoutRequiredError(): void
    {
        $field = $this->createField(value: new TimeOfDay(hour: 8, minute: 0));

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['time' => ''])));
        $this->assertNull($field->getValueAsTimeOfDay());
    }

    public function testRequiredErrorForEmptyInput(): void
    {
        $field = $this->createField(requiredError: HtmlText::fromHtml(html: 'Required'));

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['time' => '  '])));
        $this->assertSame('Required', $field->errorCollection->getFirstError()->render());
    }

    public function testMissingKeyGivesEmptyValue(): void
    {
        $field = $this->createField(value: new TimeOfDay(hour: 8, minute: 0));

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: [])));
        $this->assertNull($field->getValueAsTimeOfDay());
    }

    public function testArrayInputIsRejectedAndResetsValue(): void
    {
        $field = $this->createField(value: new TimeOfDay(hour: 8, minute: 0));

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['time' => ['x']])));
        $this->assertNull($field->getValueAsTimeOfDay());
        $this->assertSame('The invalid input was ignored.', $field->errorCollection->getFirstError()->render());
    }

    public function testValueHasChangedComparesTheTime(): void
    {
        $field = $this->createField(value: new TimeOfDay(hour: 8, minute: 30));

        $field->validate(input: FormInput::fromArray(data: ['time' => '08:30:00']));
        $this->assertFalse($field->valueHasChanged());

        $field->validate(input: FormInput::fromArray(data: ['time' => '08:30:01']));
        $this->assertTrue($field->valueHasChanged());
    }

    public function testSetValueChangesTheCurrentValueOnly(): void
    {
        $field = $this->createField(value: new TimeOfDay(hour: 8, minute: 30));

        $field->setValue(value: TimeOfDay::fromString(time: '09:15'));

        $this->assertSame('09:15:00', $field->getValueAsTimeOfDay()?->toString());
        $this->assertTrue($field->valueHasChanged());
    }

    public function testSetValueNullEmptiesTheField(): void
    {
        $field = $this->createField(value: new TimeOfDay(hour: 8, minute: 30));

        $field->setValue(value: null);

        $this->assertNull($field->getValueAsTimeOfDay());
    }

    public function testSetValueClearsKeptInvalidInput(): void
    {
        $field = $this->createField();
        $field->validate(input: FormInput::fromArray(data: ['time' => '25:00']));

        $field->setValue(value: new TimeOfDay(hour: 1, minute: 2));

        $this->assertSame('01:02:00', $field->getValueAsTimeOfDay()?->toString());
    }

    public function testFieldHasNoGetValueAsString(): void
    {
        $this->assertFalse(new ReflectionClass(objectOrClass: TimeField::class)->hasMethod(name: 'getValueAsString'));
    }
}
