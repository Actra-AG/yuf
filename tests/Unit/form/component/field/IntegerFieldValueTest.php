<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\IntegerField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\form\InitialValueIntegerField;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use UnexpectedValueException;

/**
 * IntegerField (`?int`) replaces `AmountField(valueIsFloat: false)`. The accepted formats are those of AmountParser.
 */
final class IntegerFieldValueTest extends TestCase
{
    private function createField(?int $initialValue = null, ?HtmlText $requiredError = null): IntegerField
    {
        return new IntegerField(
            name: 'amount',
            label: HtmlText::encoded(textContent: 'Amount'),
            initialValue: $initialValue,
            requiredError: $requiredError,
        );
    }

    public function testEmptyFieldHasNoValue(): void
    {
        $field = $this->createField();

        $this->assertNull($field->getValueAsInt());
        $this->assertTrue($field->isValueEmpty());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function initialValueProvider(): iterable
    {
        yield 'positive' => [12];
        yield 'zero' => [0];
        yield 'negative' => [-3];
        yield 'int max' => [PHP_INT_MAX];
        yield 'int min' => [PHP_INT_MIN];
    }

    #[DataProvider('initialValueProvider')]
    public function testInitialValueIsReturned(int $initialValue): void
    {
        $field = $this->createField(initialValue: $initialValue);

        $this->assertSame($initialValue, $field->getValueAsInt());
        $this->assertFalse($field->isValueEmpty());
        $this->assertFalse($field->valueHasChanged());
    }

    /**
     * @return iterable<string, array{string, ?int}>
     */
    public static function validInputProvider(): iterable
    {
        yield 'integer' => ['12', 12];
        yield 'zero' => ['0', 0];
        yield 'negative' => ['-5', -5];
        yield 'plus sign' => ['+5', 5];
        yield 'leading zeros' => ['007', 7];
        yield 'negative zero' => ['-0', 0];
        yield 'surrounding whitespace' => [" \t12\n", 12];
        yield 'zero-width space' => ["1\u{200B}2", 12];
        yield 'empty string' => ['', null];
        yield 'only whitespace' => ["  \t", null];
        yield 'int max' => [(string) PHP_INT_MAX, PHP_INT_MAX];
        yield 'int min' => [(string) PHP_INT_MIN, PHP_INT_MIN];
    }

    #[DataProvider('validInputProvider')]
    public function testValidInputIsParsed(string $input, ?int $expected): void
    {
        $field = $this->createField();

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['amount' => $input])));
        $this->assertSame($expected, $field->getValueAsInt());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidInputProvider(): iterable
    {
        yield 'decimal' => ['1.5'];
        yield 'decimal with zero' => ['1.0'];
        yield 'trailing dot' => ['1.'];
        yield 'leading dot' => ['.5'];
        yield 'exponent notation' => ['1e3'];
        yield 'sign only' => ['-'];
        yield 'double sign' => ['+-5'];
        yield 'whitespace between sign and digits' => ['- 5'];
        yield 'whitespace inside' => ['1 2'];
        yield 'thousands separator' => ["1'000"];
        yield 'text' => ['abc'];
        yield 'hex' => ['0x1A'];
        yield 'above int range' => ['9223372036854775808'];
        yield 'below int range' => ['-9223372036854775809'];
        yield 'huge' => [str_repeat(string: '9', times: 400)];
    }

    #[DataProvider('invalidInputProvider')]
    public function testInvalidInputIsAnErrorAndKeptForRendering(string $input): void
    {
        $field = $this->createField();

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['amount' => $input])));
        $this->assertSame('The given value is invalid.', $field->errorCollection->getFirstError()->render());
        $this->assertStringContainsString(
            'value="' . htmlspecialchars(string: $input, flags: ENT_QUOTES) . '"',
            $field->render(),
        );
    }

    #[DataProvider('invalidInputProvider')]
    public function testGetterThrowsForInvalidInput(string $input): void
    {
        $field = $this->createField();
        $field->validate(input: FormInput::fromArray(data: ['amount' => $input]));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('field amount');

        $field->getValueAsInt();
    }

    public function testInvalidInputGivesExactlyOneError(): void
    {
        $field = $this->createField(requiredError: HtmlText::encoded(textContent: 'Required'));

        $field->validate(input: FormInput::fromArray(data: ['amount' => 'abc']));

        $this->assertSame(1, $field->errorCollection->count());
    }

    public function testIndividualInvalidErrorWinsOverTheFormMessage(): void
    {
        $field = new IntegerField(
            name: 'amount',
            label: HtmlText::encoded(textContent: 'Amount'),
            individualInvalidError: HtmlText::encoded(textContent: 'Not a whole number'),
        );

        $field->validate(input: FormInput::fromArray(data: ['amount' => '1.5']));

        $this->assertSame('Not a whole number', $field->errorCollection->getFirstError()->render());
    }

    public function testInvalidErrorTextComesFromTheFormMessages(): void
    {
        $field = $this->createField();
        $field->messages = FormMessages::german();

        $field->validate(input: FormInput::fromArray(data: ['amount' => 'abc']));

        $this->assertSame('Der angegebene Wert ist ungültig.', $field->errorCollection->getFirstError()->render());
    }

    public function testRequiredErrorForEmptyAndWhitespaceInput(): void
    {
        foreach (['', '   '] as $input) {
            $field = $this->createField(requiredError: HtmlText::encoded(textContent: 'Required'));

            $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['amount' => $input])));
            $this->assertSame('Required', $field->errorCollection->getFirstError()->render());
        }
    }

    public function testNonRequiredFieldAcceptsEmptyInput(): void
    {
        $field = $this->createField(initialValue: 5);

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['amount' => ''])));
        $this->assertNull($field->getValueAsInt());
    }

    public function testMissingKeyGivesEmptyValue(): void
    {
        $field = $this->createField(initialValue: 5);

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: [])));
        $this->assertNull($field->getValueAsInt());
    }

    public function testArrayInputIsRejectedAndResetsValue(): void
    {
        $field = $this->createField(initialValue: 5);

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['amount' => ['1']])));
        $this->assertNull($field->getValueAsInt());
        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('The invalid input was ignored.', $field->errorCollection->getFirstError()->render());
    }

    public function testPostedInputIsRenderedAsCanonicalNumber(): void
    {
        $field = $this->createField();
        $field->validate(input: FormInput::fromArray(data: ['amount' => ' +007 ']));

        $this->assertStringContainsString('value="7"', $field->render());
    }

    public function testValueHasChangedComparesWithTheInitialValue(): void
    {
        $field = $this->createField(initialValue: 5);

        $field->validate(input: FormInput::fromArray(data: ['amount' => ' 5 ']));
        $this->assertFalse($field->valueHasChanged());

        $field->validate(input: FormInput::fromArray(data: ['amount' => '6']));
        $this->assertTrue($field->valueHasChanged());
    }

    public function testValueHasChangedForInvalidInput(): void
    {
        $field = $this->createField(initialValue: 5);

        $field->validate(input: FormInput::fromArray(data: ['amount' => 'abc']));

        $this->assertTrue($field->valueHasChanged());
    }

    public function testSetValueChangesTheCurrentValueOnly(): void
    {
        $field = $this->createField(initialValue: 5);

        $field->setValue(value: 9);

        $this->assertSame(9, $field->getValueAsInt());
        $this->assertTrue($field->valueHasChanged());

        $field->setValue(value: 5);

        $this->assertFalse($field->valueHasChanged());
    }

    public function testSetValueNullEmptiesTheField(): void
    {
        $field = $this->createField(initialValue: 5);

        $field->setValue(value: null);

        $this->assertNull($field->getValueAsInt());
        $this->assertTrue($field->isValueEmpty());
        $this->assertTrue($field->valueHasChanged());
    }

    public function testSetValueClearsKeptInvalidInputWithoutAnError(): void
    {
        $field = $this->createField();
        $field->validate(input: FormInput::fromArray(data: ['amount' => 'abc']));
        $errorCount = $field->errorCollection->count();

        $field->setValue(value: 3);

        $this->assertSame(3, $field->getValueAsInt());
        $this->assertSame($errorCount, $field->errorCollection->count());
    }

    public function testSetInitialValueSetsCurrentAndInitialValue(): void
    {
        $field = new InitialValueIntegerField(name: 'amount', label: HtmlText::encoded(textContent: 'Amount'));

        $field->fill(value: 42);

        $this->assertSame(42, $field->getValueAsInt());
        $this->assertFalse($field->valueHasChanged());
    }

    public function testSetInitialValueAfterValidationThrows(): void
    {
        $field = new InitialValueIntegerField(name: 'amount', label: HtmlText::encoded(textContent: 'Amount'));
        $field->validate(input: FormInput::fromArray(data: []));

        $this->expectException(LogicException::class);

        $field->fill(value: 1);
    }

    public function testFieldHasNoGetValueAsString(): void
    {
        $class = new ReflectionClass(objectOrClass: IntegerField::class);

        $this->assertFalse($class->hasMethod(name: 'getValueAsString'));
        $this->assertFalse($class->hasMethod(name: 'getValueAsFloat'));
    }
}
