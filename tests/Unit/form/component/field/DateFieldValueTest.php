<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\DateField;
use actra\yuf\form\FormInput;
use actra\yuf\html\HtmlText;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use UnexpectedValueException;

/**
 * DateField holds a `?DateTimeImmutable` (at 00:00:00).
 */
final class DateFieldValueTest extends TestCase
{
    private function createField(?DateTimeImmutable $value = null, ?HtmlText $requiredError = null): DateField
    {
        return new DateField(
            name: 'date',
            label: HtmlText::encoded(textContent: 'Date'),
            value: $value,
            invalidError: HtmlText::encoded(textContent: 'Invalid'),
            requiredError: $requiredError,
        );
    }

    public function testEmptyFieldHasNoValue(): void
    {
        $field = $this->createField();

        $this->assertNull($field->getValueAsDateTimeImmutable());
        $this->assertTrue($field->isValueEmpty());
    }

    public function testInitialValueIsReturnedAtMidnight(): void
    {
        $field = $this->createField(value: new DateTimeImmutable(datetime: '2020-01-02 13:45:10'));

        $this->assertSame('2020-01-02 00:00:00', $field->getValueAsDateTimeImmutable()?->format(format: 'Y-m-d H:i:s'));
        $this->assertStringContainsString('value="2020-01-02"', $field->render());
        $this->assertFalse($field->valueHasChanged());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validInputProvider(): iterable
    {
        yield 'ISO date' => ['2020-02-03', '2020-02-03'];
        yield 'ISO date without leading zeros' => ['2020-2-3', '2020-02-03'];
        yield 'Swiss date format' => ['03.02.2020', '2020-02-03'];
        yield 'Swiss date format without leading zeros' => ['3.2.2020', '2020-02-03'];
        yield 'leap day' => ['2020-02-29', '2020-02-29'];
        yield 'surrounding whitespace' => [' 2020-02-03 ', '2020-02-03'];
        yield 'last day of the year' => ['31.12.1999', '1999-12-31'];
    }

    #[DataProvider('validInputProvider')]
    public function testValidDateIsParsedAndRenderedAsIsoDate(string $input, string $expected): void
    {
        $field = $this->createField();

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['date' => $input])));
        $this->assertSame(
            $expected . ' 00:00:00',
            $field->getValueAsDateTimeImmutable()?->format(format: 'Y-m-d H:i:s'),
        );
        $this->assertStringContainsString('value="' . $expected . '"', $field->render());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidInputProvider(): iterable
    {
        yield 'impossible day' => ['2020-02-30'];
        yield 'not a leap year' => ['2021-02-29'];
        yield 'impossible month' => ['2020-13-01'];
        yield 'impossible Swiss date' => ['31.02.2020'];
        yield 'text' => ['tomorrow'];
        yield 'two digit year' => ['3.2.20'];
        yield 'slashes' => ['2020/02/03'];
        yield 'date with time' => ['2020-02-03 10:00'];
        yield 'zero day' => ['2020-02-00'];
    }

    #[DataProvider('invalidInputProvider')]
    public function testInvalidDateIsAnErrorAndKeptForRendering(string $input): void
    {
        $field = $this->createField();

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['date' => $input])));
        $this->assertSame('Invalid', $field->errorCollection->getFirstError()->render());
        $this->assertSame(1, $field->errorCollection->count());
        $this->assertStringContainsString('value="' . $input . '"', $field->render());
    }

    #[DataProvider('invalidInputProvider')]
    public function testGetterThrowsForInvalidDate(string $input): void
    {
        $field = $this->createField();
        $field->validate(input: FormInput::fromArray(data: ['date' => $input]));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('field date');

        $field->getValueAsDateTimeImmutable();
    }

    public function testEmptyAndWhitespaceInputAreValidWithoutRequiredError(): void
    {
        foreach (['', '  '] as $input) {
            $field = $this->createField(value: new DateTimeImmutable(datetime: '2020-01-02'));

            $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['date' => $input])));
            $this->assertNull($field->getValueAsDateTimeImmutable());
        }
    }

    public function testRequiredErrorForEmptyInput(): void
    {
        $field = $this->createField(requiredError: HtmlText::encoded(textContent: 'Required'));

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['date' => ' '])));
        $this->assertSame('Required', $field->errorCollection->getFirstError()->render());
    }

    public function testMissingKeyGivesEmptyValue(): void
    {
        $field = $this->createField(value: new DateTimeImmutable(datetime: '2020-02-03'));

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: [])));
        $this->assertNull($field->getValueAsDateTimeImmutable());
    }

    public function testArrayInputIsRejectedAndResetsValue(): void
    {
        $field = $this->createField(value: new DateTimeImmutable(datetime: '2020-01-02'));

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['date' => ['x']])));
        $this->assertNull($field->getValueAsDateTimeImmutable());
        $this->assertSame('The invalid input was ignored.', $field->errorCollection->getFirstError()->render());
    }

    public function testValueHasChangedComparesTheDate(): void
    {
        $field = $this->createField(value: new DateTimeImmutable(datetime: '2020-01-02'));

        $field->validate(input: FormInput::fromArray(data: ['date' => '2.1.2020']));
        $this->assertFalse($field->valueHasChanged());

        $field->validate(input: FormInput::fromArray(data: ['date' => '2020-01-03']));
        $this->assertTrue($field->valueHasChanged());
    }

    public function testSetValueChangesTheCurrentValueOnly(): void
    {
        $field = $this->createField(value: new DateTimeImmutable(datetime: '2020-01-02'));

        $field->setValue(value: new DateTimeImmutable(datetime: '2021-05-06 08:00'));

        $this->assertSame('2021-05-06', $field->getValueAsDateTimeImmutable()?->format(format: 'Y-m-d'));
        $this->assertTrue($field->valueHasChanged());
    }

    public function testSetValueNullEmptiesTheField(): void
    {
        $field = $this->createField(value: new DateTimeImmutable(datetime: '2020-01-02'));

        $field->setValue(value: null);

        $this->assertNull($field->getValueAsDateTimeImmutable());
    }

    public function testSetValueClearsKeptInvalidInput(): void
    {
        $field = $this->createField();
        $field->validate(input: FormInput::fromArray(data: ['date' => 'tomorrow']));

        $field->setValue(value: new DateTimeImmutable(datetime: '2020-01-02'));

        $this->assertSame('2020-01-02', $field->getValueAsDateTimeImmutable()?->format(format: 'Y-m-d'));
    }

    public function testFieldHasNoGetValueAsString(): void
    {
        $this->assertFalse(new ReflectionClass(objectOrClass: DateField::class)->hasMethod(name: 'getValueAsString'));
    }
}
