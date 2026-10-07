<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\DecimalField;
use actra\yuf\form\FormInput;
use actra\yuf\html\HtmlText;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use UnexpectedValueException;

/**
 * DecimalField (`?string`, bcmath) is for money: a fixed number of decimals, more decimals are rejected, never rounded.
 */
final class DecimalFieldValueTest extends TestCase
{
    private function createField(
        int $scale = 2,
        ?string $initialValue = null,
        ?HtmlText $requiredError = null,
    ): DecimalField {
        return new DecimalField(
            name: 'price',
            label: HtmlText::encoded(textContent: 'Price'),
            scale: $scale,
            initialValue: $initialValue,
            requiredError: $requiredError,
        );
    }

    public function testEmptyFieldHasNoValue(): void
    {
        $field = $this->createField();

        $this->assertNull($field->getValueAsDecimal());
        $this->assertTrue($field->isValueEmpty());
    }

    public function testInitialValueIsStoredWithTheScale(): void
    {
        $field = $this->createField(initialValue: '12.5');

        $this->assertSame('12.50', $field->getValueAsDecimal());
        $this->assertStringContainsString('value="12.50"', $field->render());
        $this->assertFalse($field->valueHasChanged());
    }

    /**
     * @return iterable<string, array{int, string, ?string}>
     */
    public static function validInputProvider(): iterable
    {
        yield 'integer gets the scale' => [2, '12', '12.00'];
        yield 'exact scale' => [2, '12.50', '12.50'];
        yield 'fewer decimals' => [2, '12.5', '12.50'];
        yield 'zero' => [2, '0', '0.00'];
        yield 'zero with decimals' => [2, '0.00', '0.00'];
        yield 'negative' => [2, '-12.5', '-12.50'];
        yield 'negative zero has no sign' => [2, '-0', '0.00'];
        yield 'negative zero with decimals has no sign' => [2, '-0.00', '0.00'];
        yield 'plus sign' => [2, '+5', '5.00'];
        yield 'leading zeros' => [2, '007.5', '7.50'];
        yield 'leading dot' => [2, '.5', '0.50'];
        yield 'trailing dot' => [2, '1.', '1.00'];
        yield 'surrounding whitespace' => [2, " \t12.5\n", '12.50'];
        yield 'scale 0' => [0, '12', '12'];
        yield 'scale 0 with trailing dot' => [0, '12.', '12'];
        yield 'scale 3' => [3, '1.5', '1.500'];
        yield 'five centimes' => [2, '0.05', '0.05'];
        yield 'trailing zeros beyond the scale' => [2, '12.500', '12.50'];
        yield 'scale 0 with zero decimals' => [0, '12.00', '12'];
        $nines = str_repeat(string: '9', times: 60);
        $ones = str_repeat(string: '1', times: 80);
        yield 'very large number' => [2, $nines . '.1', $nines . '.10'];
        yield 'very large negative number' => [2, '-' . $ones, '-' . $ones . '.00'];
        yield 'empty' => [2, '', null];
        yield 'only whitespace' => [2, '   ', null];
    }

    #[DataProvider('validInputProvider')]
    public function testValidInputIsStoredCanonical(int $scale, string $input, ?string $expected): void
    {
        $field = $this->createField(scale: $scale);

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['price' => $input])));
        $this->assertSame($expected, $field->getValueAsDecimal());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function invalidInputProvider(): iterable
    {
        yield 'more decimals than the scale' => [2, '12.555'];
        yield 'one decimal too many' => [2, '0.001'];
        yield 'significant decimal after trailing zeros' => [2, '12.5001'];
        yield 'scale 0 with a decimal' => [0, '12.5'];
        yield 'decimal comma' => [2, '12,5'];
        yield 'exponent notation' => [2, '1e3'];
        yield 'thousands separator' => [2, "1'000.50"];
        yield 'sign only' => [2, '-'];
        yield 'whitespace inside' => [2, '1 2'];
        yield 'text' => [2, 'abc'];
        yield 'currency' => [2, 'CHF 12.50'];
    }

    #[DataProvider('invalidInputProvider')]
    public function testInvalidInputIsAnErrorAndKeptForRendering(int $scale, string $input): void
    {
        $field = $this->createField(scale: $scale);

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['price' => $input])));
        $this->assertSame('The given value is invalid.', $field->errorCollection->getFirstError()->render());
        $this->assertStringContainsString(
            'value="' . htmlspecialchars(string: $input, flags: ENT_QUOTES) . '"',
            $field->render(),
        );
    }

    #[DataProvider('invalidInputProvider')]
    public function testGetterThrowsForInvalidInput(int $scale, string $input): void
    {
        $field = $this->createField(scale: $scale);
        $field->validate(input: FormInput::fromArray(data: ['price' => $input]));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('field price');

        $field->getValueAsDecimal();
    }

    public function testTooManyDecimalsAreNeverRounded(): void
    {
        $field = $this->createField(initialValue: '1.00');

        $field->validate(input: FormInput::fromArray(data: ['price' => '1.999']));

        $this->assertTrue($field->hasErrors(withChildElements: false));
        $this->assertStringContainsString('value="1.999"', $field->render());
    }

    public function testIndividualInvalidError(): void
    {
        $field = new DecimalField(
            name: 'price',
            label: HtmlText::encoded(textContent: 'Price'),
            scale: 2,
            individualInvalidError: HtmlText::encoded(textContent: 'At most 2 decimals'),
        );

        $field->validate(input: FormInput::fromArray(data: ['price' => '1.234']));

        $this->assertSame('At most 2 decimals', $field->errorCollection->getFirstError()->render());
    }

    public function testRequiredErrorForEmptyInput(): void
    {
        $field = $this->createField(requiredError: HtmlText::encoded(textContent: 'Required'));

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['price' => ' '])));
        $this->assertSame('Required', $field->errorCollection->getFirstError()->render());
    }

    public function testMissingKeyGivesEmptyValue(): void
    {
        $field = $this->createField(initialValue: '5');

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: [])));
        $this->assertNull($field->getValueAsDecimal());
    }

    public function testArrayInputIsRejectedAndResetsValue(): void
    {
        $field = $this->createField(initialValue: '5');

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['price' => ['1']])));
        $this->assertNull($field->getValueAsDecimal());
        $this->assertSame('The invalid input was ignored.', $field->errorCollection->getFirstError()->render());
    }

    public function testValueHasChangedComparesTheCanonicalValues(): void
    {
        $field = $this->createField(initialValue: '12.50');

        $field->validate(input: FormInput::fromArray(data: ['price' => '12.5']));
        $this->assertFalse($field->valueHasChanged());

        $field->validate(input: FormInput::fromArray(data: ['price' => '12.51']));
        $this->assertTrue($field->valueHasChanged());
    }

    public function testSetValueChangesTheCurrentValueOnly(): void
    {
        $field = $this->createField(initialValue: '1');

        $field->setValue(value: '2.5');

        $this->assertSame('2.50', $field->getValueAsDecimal());
        $this->assertTrue($field->valueHasChanged());
    }

    public function testSetValueNullEmptiesTheField(): void
    {
        $field = $this->createField(initialValue: '1');

        $field->setValue(value: null);

        $this->assertNull($field->getValueAsDecimal());
    }

    public function testSetValueClearsKeptInvalidInput(): void
    {
        $field = $this->createField();
        $field->validate(input: FormInput::fromArray(data: ['price' => 'abc']));

        $field->setValue(value: '3');

        $this->assertSame('3.00', $field->getValueAsDecimal());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function illegalValueProvider(): iterable
    {
        yield 'text' => ['abc'];
        yield 'empty string' => [''];
        yield 'too many decimals' => ['1.234'];
        yield 'comma' => ['1,5'];
    }

    #[DataProvider('illegalValueProvider')]
    public function testSetValueRejectsAnIllegalString(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('field price');

        $this->createField()->setValue(value: $value);
    }

    #[DataProvider('illegalValueProvider')]
    public function testConstructorRejectsAnIllegalInitialValue(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createField(initialValue: $value);
    }

    public function testNegativeScaleIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createField(scale: -1);
    }

    public function testFieldHasOnlyTheDecimalGetter(): void
    {
        $class = new ReflectionClass(objectOrClass: DecimalField::class);

        $this->assertFalse($class->hasMethod(name: 'getValueAsString'));
        $this->assertFalse($class->hasMethod(name: 'getValueAsFloat'));
    }
}
