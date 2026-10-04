<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\HiddenField;
use actra\yuf\form\FormMessages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class HiddenFieldValueTest extends TestCase
{
    public function testValueIsEmptyStringAfterConstructionWithoutValue(): void
    {
        $this->assertSame('', new HiddenField(name: 'hidden')->getValueAsString());
    }

    public function testConstructorValueIsKeptAsString(): void
    {
        $this->assertSame('text', new HiddenField(name: 'hidden', value: 'text')->getValueAsString());
    }

    public function testStringInputIsNotTrimmed(): void
    {
        $field = new HiddenField(name: 'hidden');

        $field->validate(inputData: ['hidden' => ' a ']);

        $this->assertSame(' a ', $field->getValueAsString());
    }

    public function testConstructorValueIsNotTrimmed(): void
    {
        $this->assertSame(' a ', new HiddenField(name: 'hidden', value: ' a ')->getValueAsString());
    }

    public function testZeroWidthSpacesAreRemoved(): void
    {
        $field = new HiddenField(name: 'hidden');

        $field->validate(inputData: ['hidden' => "a\u{200B}b"]);

        $this->assertSame('ab', $field->getValueAsString());
    }

    public function testValueIsEmptyStringAfterValidationWithMissingKey(): void
    {
        $field = new HiddenField(name: 'hidden', value: '5');

        $this->assertTrue($field->validate(inputData: []));

        $this->assertSame('', $field->getValueAsString());
    }

    public function testArrayInputIsRejectedAndResetsValue(): void
    {
        $field = new HiddenField(name: 'hidden', value: '5');

        $isValid = $field->validate(inputData: ['hidden' => ['x']]);

        $this->assertFalse($isValid);
        $this->assertSame('', $field->getValueAsString());
        $this->assertSame(1, $field->errorCollection->count());
    }

    public function testSetValueChangesCurrentValueOnly(): void
    {
        $field = new HiddenField(name: 'hidden', value: 'a');

        $field->setValue(value: 'b');

        $this->assertSame('b', $field->getValueAsString());
        $this->assertTrue($field->valueHasChanged());
    }

    public function testRenderValueEncodesValue(): void
    {
        $this->assertSame('a&lt;b', new HiddenField(name: 'hidden', value: 'a<b')->renderValue());
    }

    /**
     * @return iterable<string, array{string, ?int}>
     */
    public static function valueAsIntProvider(): iterable
    {
        yield 'empty string' => ['', null];
        yield 'whitespace only' => [' ', null];
        yield 'integer string' => ['12', 12];
        yield 'zero' => ['0', 0];
        yield 'plus sign' => ['+5', 5];
        yield 'leading zeros' => ['007', 7];
        yield 'negative string' => ['-5', -5];
        yield 'int max' => [(string)PHP_INT_MAX, PHP_INT_MAX];
    }

    #[DataProvider('valueAsIntProvider')]
    public function testGetValueAsIntConvertsConstructorValue(string $value, ?int $expected): void
    {
        $field = new HiddenField(name: 'hidden', value: $value);

        $this->assertSame($expected, $field->getValueAsInt());
    }

    public function testGetValueAsIntConvertsPostedString(): void
    {
        $field = new HiddenField(name: 'hidden');

        $field->validate(inputData: ['hidden' => ' +7 ']);

        $this->assertSame(7, $field->getValueAsInt());
    }

    public function testGetValueAsIntIsNullAfterValidationWithMissingKey(): void
    {
        $field = new HiddenField(name: 'hidden', value: '5');

        $field->validate(inputData: []);

        $this->assertNull($field->getValueAsInt());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notAnIntegerProvider(): iterable
    {
        yield 'decimal string' => ['1.5'];
        yield 'exponent' => ['1e3'];
        yield 'text' => ['abc'];
        yield 'overflow' => ['9223372036854775808'];
        yield 'negative overflow' => ['-9223372036854775809'];
    }

    #[DataProvider('notAnIntegerProvider')]
    public function testGetValueAsIntThrows(string $value): void
    {
        $field = new HiddenField(name: 'hidden', value: $value);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('field hidden');

        $field->getValueAsInt();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function manipulatedIntegerInputProvider(): iterable
    {
        yield 'text' => ['abc'];
        yield 'decimal' => ['1.5'];
        yield 'out of int range' => ['9223372036854775808'];
    }

    #[DataProvider('manipulatedIntegerInputProvider')]
    public function testIntegerFieldRejectsManipulatedInputWithValidationError(string $input): void
    {
        $field = new HiddenField(name: 'hidden', valueIsInt: true);

        $this->assertFalse($field->validate(inputData: ['hidden' => $input]));
        $this->assertSame('The given value is invalid.', $field->errorCollection->getFirstError()->render());
    }

    public function testIntegerFieldErrorTextComesFromTheFormMessages(): void
    {
        $field = new HiddenField(name: 'hidden', valueIsInt: true);
        $field->messages = FormMessages::german();

        $field->validate(inputData: ['hidden' => 'abc']);

        $this->assertSame('Der angegebene Wert ist ungültig.', $field->errorCollection->getFirstError()->render());
    }

    public function testIntegerFieldAcceptsIntegerInput(): void
    {
        $field = new HiddenField(name: 'hidden', valueIsInt: true);

        $this->assertTrue($field->validate(inputData: ['hidden' => '42']));
        $this->assertSame(42, $field->getValueAsInt());
    }

    public function testIntegerFieldAcceptsMissingKey(): void
    {
        $field = new HiddenField(name: 'hidden', value: '5', valueIsInt: true);

        $this->assertTrue($field->validate(inputData: []));
        $this->assertNull($field->getValueAsInt());
    }

    public function testIntegerFieldWithIntegerConstructorValueHasNoError(): void
    {
        $field = new HiddenField(name: 'hidden', value: 'abc', valueIsInt: true);

        $this->assertFalse($field->hasErrors(withChildElements: false));
    }

    public function testFieldWithoutIntegerOptionAcceptsAnyString(): void
    {
        $field = new HiddenField(name: 'hidden');

        $this->assertTrue($field->validate(inputData: ['hidden' => 'abc']));
    }
}