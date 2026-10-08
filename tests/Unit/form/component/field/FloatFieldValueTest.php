<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\FloatField;
use actra\yuf\form\FormInput;
use actra\yuf\html\HtmlText;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use UnexpectedValueException;

/**
 * FloatField (`?float`) replaces `AmountField(valueIsFloat: true)`, for measurements.
 */
final class FloatFieldValueTest extends TestCase
{
    private function createField(?float $initialValue = null, ?HtmlText $requiredError = null): FloatField
    {
        return new FloatField(
            name: 'amount',
            label: HtmlText::fromHtml(html: 'Amount'),
            initialValue: $initialValue,
            requiredError: $requiredError,
        );
    }

    public function testEmptyFieldHasNoValue(): void
    {
        $field = $this->createField();

        $this->assertNull($field->getValueAsFloat());
        $this->assertTrue($field->isValueEmpty());
    }

    public function testInitialValueIsReturnedAndRendered(): void
    {
        $field = $this->createField(initialValue: 1.5);

        $this->assertSame(1.5, $field->getValueAsFloat());
        $this->assertStringContainsString('value="1.5"', $field->render());
        $this->assertFalse($field->valueHasChanged());
    }

    public function testZeroIsAValue(): void
    {
        $field = $this->createField(initialValue: 0.0);

        $this->assertSame(0.0, $field->getValueAsFloat());
        $this->assertFalse($field->isValueEmpty());
    }

    /**
     * @return iterable<string, array{string, ?float}>
     */
    public static function validInputProvider(): iterable
    {
        yield 'integer' => ['12', 12.0];
        yield 'decimal' => ['1.5', 1.5];
        yield 'negative decimal' => ['-1.5', -1.5];
        yield 'plus sign' => ['+1.5', 1.5];
        yield 'leading zeros' => ['007.50', 7.5];
        yield 'trailing dot' => ['1.', 1.0];
        yield 'leading dot' => ['.5', 0.5];
        yield 'surrounding whitespace' => [" \t1.5\n", 1.5];
        yield 'above int range' => ['9223372036854775808', 9223372036854775808.0];
        yield 'empty' => ['', null];
        yield 'only whitespace' => ['  ', null];
    }

    #[DataProvider('validInputProvider')]
    public function testValidInputIsParsed(string $input, ?float $expected): void
    {
        $field = $this->createField();

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['amount' => $input])));
        $this->assertSame($expected, $field->getValueAsFloat());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidInputProvider(): iterable
    {
        yield 'exponent notation' => ['1e3'];
        yield 'decimal exponent notation' => ['1.5E-3'];
        yield 'sign only' => ['-'];
        yield 'whitespace inside' => ['1 5'];
        yield 'text' => ['abc'];
        yield 'decimal comma' => ['1,5'];
        yield 'thousands separator' => ["1'000.5"];
        yield 'too large for a float' => [str_repeat(string: '9', times: 400)];
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

        $field->getValueAsFloat();
    }

    public function testRequiredErrorForEmptyInput(): void
    {
        $field = $this->createField(requiredError: HtmlText::fromHtml(html: 'Required'));

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['amount' => '  '])));
        $this->assertSame('Required', $field->errorCollection->getFirstError()->render());
    }

    public function testMissingKeyGivesEmptyValue(): void
    {
        $field = $this->createField(initialValue: 5.5);

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: [])));
        $this->assertNull($field->getValueAsFloat());
    }

    public function testArrayInputIsRejectedAndResetsValue(): void
    {
        $field = $this->createField(initialValue: 5.5);

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['amount' => ['1']])));
        $this->assertNull($field->getValueAsFloat());
        $this->assertSame(1, $field->errorCollection->count());
    }

    public function testPostedInputIsRenderedAsCanonicalNumber(): void
    {
        $field = $this->createField();
        $field->validate(input: FormInput::fromArray(data: ['amount' => ' +007.50 ']));

        $this->assertStringContainsString('value="7.5"', $field->render());
    }

    /**
     * @return iterable<string, array{float, string}>
     */
    public static function roundTripProvider(): iterable
    {
        yield 'whole number' => [1.0, '1'];
        yield 'negative' => [-2.25, '-2.25'];
        yield 'float sum with many digits' => [0.1 + 0.2, '0.30000000000000004'];
        yield 'very small' => [0.00001, '0.00001'];
        yield 'very large' => [1.0E+25, '10000000000000000905969664'];
    }

    #[DataProvider('roundTripProvider')]
    public function testValueIsRenderedWithoutExponentAndKeepsItsPrecision(float $value, string $expectedText): void
    {
        $field = $this->createField(initialValue: $value);

        $this->assertSame($value, $field->getValueAsFloat());
        $this->assertStringContainsString('value="' . $expectedText . '"', $field->render());
    }

    public function testValueHasChangedComparesWithTheInitialValue(): void
    {
        $field = $this->createField(initialValue: 1.5);

        $field->validate(input: FormInput::fromArray(data: ['amount' => '1.50']));
        $this->assertFalse($field->valueHasChanged());

        $field->validate(input: FormInput::fromArray(data: ['amount' => '1.6']));
        $this->assertTrue($field->valueHasChanged());
    }

    public function testSetValueChangesTheCurrentValueOnly(): void
    {
        $field = $this->createField(initialValue: 1.5);

        $field->setValue(value: 2.5);

        $this->assertSame(2.5, $field->getValueAsFloat());
        $this->assertTrue($field->valueHasChanged());
    }

    public function testSetValueAcceptsAnIntLikeAFloatParameter(): void
    {
        $field = $this->createField();

        $field->setValue(value: 3);

        $this->assertSame(3.0, $field->getValueAsFloat());
    }

    public function testSetValueNullEmptiesTheField(): void
    {
        $field = $this->createField(initialValue: 1.5);

        $field->setValue(value: null);

        $this->assertNull($field->getValueAsFloat());
    }

    public function testSetValueClearsKeptInvalidInput(): void
    {
        $field = $this->createField();
        $field->validate(input: FormInput::fromArray(data: ['amount' => 'abc']));

        $field->setValue(value: 1.5);

        $this->assertSame(1.5, $field->getValueAsFloat());
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function notFiniteProvider(): iterable
    {
        yield 'infinity' => [INF];
        yield 'negative infinity' => [-INF];
        yield 'not a number' => [NAN];
    }

    #[DataProvider('notFiniteProvider')]
    public function testSetValueRejectsANumberThatIsNotFinite(float $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('field amount');

        $this->createField()->setValue(value: $value);
    }

    #[DataProvider('notFiniteProvider')]
    public function testConstructorRejectsANumberThatIsNotFinite(float $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createField(initialValue: $value);
    }

    public function testFieldHasNoGetValueAsStringOrGetValueAsInt(): void
    {
        $this->assertFalse(new ReflectionClass(objectOrClass: FloatField::class)->hasMethod(name: 'getValueAsString'));
        $this->assertFalse(new ReflectionClass(objectOrClass: FloatField::class)->hasMethod(name: 'getValueAsInt'));
    }
}
