<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\HiddenIntegerField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * HiddenIntegerField replaces `HiddenField(valueIsInt: true)`: manipulated input is a validation error, so the getter
 * never fails after a successful validation.
 */
final class HiddenIntegerFieldValueTest extends TestCase
{
    public function testEmptyFieldHasNoValue(): void
    {
        $field = new HiddenIntegerField(name: 'id');

        $this->assertNull($field->getValueAsInt());
        $this->assertTrue($field->isValueEmpty());
    }

    public function testConstructorValueIsReturnedAndRendered(): void
    {
        $field = new HiddenIntegerField(name: 'id', value: 7);

        $this->assertSame(7, $field->getValueAsInt());
        $this->assertSame('<input type="hidden" name="id" value="7">', $field->render());
        $this->assertFalse($field->valueHasChanged());
    }

    /**
     * @return iterable<string, array{string, ?int}>
     */
    public static function validInputProvider(): iterable
    {
        yield 'integer' => ['42', 42];
        yield 'zero' => ['0', 0];
        yield 'plus sign' => ['+5', 5];
        yield 'leading zeros' => ['007', 7];
        yield 'negative' => ['-5', -5];
        yield 'surrounding whitespace' => [' +7 ', 7];
        yield 'empty' => ['', null];
        yield 'only whitespace' => [' ', null];
        yield 'int max' => [(string) PHP_INT_MAX, PHP_INT_MAX];
    }

    #[DataProvider('validInputProvider')]
    public function testValidInputIsParsed(string $input, ?int $expected): void
    {
        $field = new HiddenIntegerField(name: 'id');

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['id' => $input])));
        $this->assertSame($expected, $field->getValueAsInt());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function manipulatedInputProvider(): iterable
    {
        yield 'text' => ['abc'];
        yield 'decimal' => ['1.5'];
        yield 'exponent' => ['1e3'];
        yield 'out of int range' => ['9223372036854775808'];
        yield 'negative out of int range' => ['-9223372036854775809'];
        yield 'sql injection' => ['1 OR 1=1'];
    }

    #[DataProvider('manipulatedInputProvider')]
    public function testManipulatedInputIsAValidationError(string $input): void
    {
        $field = new HiddenIntegerField(name: 'id');

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['id' => $input])));
        $this->assertSame('The given value is invalid.', $field->errorCollection->getFirstError()->render());
        $this->assertSame(1, $field->errorCollection->count());
    }

    #[DataProvider('manipulatedInputProvider')]
    public function testGetterThrowsForManipulatedInput(string $input): void
    {
        $field = new HiddenIntegerField(name: 'id');
        $field->validate(input: FormInput::fromArray(data: ['id' => $input]));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('field id');

        $field->getValueAsInt();
    }

    public function testErrorTextComesFromTheFormMessages(): void
    {
        $field = new HiddenIntegerField(name: 'id');
        $field->messages = FormMessages::german();

        $field->validate(input: FormInput::fromArray(data: ['id' => 'abc']));

        $this->assertSame('Der angegebene Wert ist ungültig.', $field->errorCollection->getFirstError()->render());
    }

    public function testMissingKeyGivesEmptyValue(): void
    {
        $field = new HiddenIntegerField(name: 'id', value: 5);

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: [])));
        $this->assertNull($field->getValueAsInt());
    }

    public function testArrayInputIsRejectedAndResetsValue(): void
    {
        $field = new HiddenIntegerField(name: 'id', value: 5);

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['id' => ['5']])));
        $this->assertNull($field->getValueAsInt());
        $this->assertSame('The invalid input was ignored.', $field->errorCollection->getFirstError()->render());
    }

    public function testManipulatedInputIsRenderedBackAsPosted(): void
    {
        $field = new HiddenIntegerField(name: 'id');
        $field->validate(input: FormInput::fromArray(data: ['id' => '1"2']));

        $this->assertStringContainsString('value="1&quot;2"', $field->render());
    }

    public function testSetValueChangesTheCurrentValueOnly(): void
    {
        $field = new HiddenIntegerField(name: 'id', value: 5);

        $field->setValue(value: 6);

        $this->assertSame(6, $field->getValueAsInt());
        $this->assertTrue($field->valueHasChanged());
    }
}
