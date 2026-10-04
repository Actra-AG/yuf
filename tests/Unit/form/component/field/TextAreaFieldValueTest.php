<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\TextAreaField;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * An array value is an intended feature of TextAreaField: a list of entries, rendered one per line.
 */
final class TextAreaFieldValueTest extends TestCase
{
    /**
     * @param null|string|list<string> $value
     */
    private function createField(null|string|array $value = null): TextAreaField
    {
        return new TextAreaField(
            name: 'text',
            label: HtmlText::encoded(textContent: 'Text'),
            value: $value
        );
    }

    public function testValueIsNullAfterConstructionWithoutValue(): void
    {
        $this->assertNull($this->createField()->getRawValue());
    }

    public function testValueIsStringAfterConstructionWithString(): void
    {
        $this->assertSame("a\nb", $this->createField(value: "a\nb")->getRawValue());
    }

    public function testValueIsArrayAfterConstructionWithArray(): void
    {
        $this->assertSame(['a', 'b'], $this->createField(value: ['a', 'b'])->getRawValue());
    }

    public function testStringInputIsStoredUntrimmedWithoutZeroWidthSpaces(): void
    {
        $field = $this->createField();

        $field->validate(inputData: ['text' => " a\u{200B}\nb "]);

        $this->assertSame(" a\nb ", $field->getRawValue());
    }

    public function testStringInputReplacesArrayValue(): void
    {
        $field = $this->createField(value: ['a']);

        $field->validate(inputData: ['text' => 'b']);

        $this->assertSame('b', $field->getRawValue());
    }

    public function testArrayInputIsRejectedForStringValue(): void
    {
        $field = $this->createField(value: 'initial');

        $isValid = $field->validate(inputData: ['text' => ['x']]);

        $this->assertFalse($isValid);
        $this->assertSame('initial', $field->getRawValue());
    }

    public function testArrayInputIsAcceptedIfConstructedWithArray(): void
    {
        $field = $this->createField(value: ['a']);

        $isValid = $field->validate(inputData: ['text' => ['b', 'c']]);

        $this->assertTrue($isValid);
        $this->assertSame(['b', 'c'], $field->getRawValue());
    }

    public function testArrayEntriesAreNotCleanedFromZeroWidthSpaces(): void
    {
        $field = $this->createField(value: ['a']);

        $field->validate(inputData: ['text' => ["b\u{200B}"]]);

        $this->assertSame(["b\u{200B}"], $field->getRawValue());
    }

    public function testValueIsNullAfterValidationWithMissingKeyForStringField(): void
    {
        $field = $this->createField(value: 'initial');

        $field->validate(inputData: []);

        $this->assertNull($field->getRawValue());
    }

    public function testValueIsEmptyArrayAfterValidationWithMissingKeyForArrayField(): void
    {
        $field = $this->createField(value: ['a']);

        $field->validate(inputData: []);

        $this->assertSame([], $field->getRawValue());
    }

    public function testSetValueWithArrayAfterStringConstructionIsRejected(): void
    {
        $field = $this->createField(value: 'initial');

        $field->setValue(value: ['a', 'b']);

        $this->assertSame('initial', $field->getRawValue());
        $this->assertTrue($field->hasErrors(withChildElements: false));
    }

    public function testRenderValueJoinsArrayEntriesWithNewlineAndEncodesThem(): void
    {
        $field = $this->createField(value: ['a<', 'b']);

        $this->assertSame('a&lt;' . PHP_EOL . 'b', $field->renderValue());
    }

    public function testRenderValueOfNullIsEmptyString(): void
    {
        $this->assertSame('', $this->createField()->renderValue());
    }

    public function testEmptyArrayValueIsEmpty(): void
    {
        $this->assertTrue($this->createField(value: [])->isValueEmpty());
    }

    /**
     * ODDITY: isValueEmpty() uses array_filter(), so the entry '0' counts as empty.
     */
    public function testArrayWithOnlyEmptyAndZeroEntriesIsEmpty(): void
    {
        $this->assertTrue($this->createField(value: ['', '0'])->isValueEmpty());
    }

    public function testGetValueAsStringIsEmptyForNull(): void
    {
        $this->assertSame('', $this->createField()->getValueAsString());
    }

    public function testGetValueAsStringReturnsStringUnchanged(): void
    {
        $this->assertSame(" a\nb ", $this->createField(value: " a\nb ")->getValueAsString());
    }

    public function testGetValueAsStringJoinsArrayEntriesWithEndOfLine(): void
    {
        $field = $this->createField(value: ['a<', 'b']);

        $this->assertSame('a<' . PHP_EOL . 'b', $field->getValueAsString());
    }

    public function testGetValueAsStringIsEmptyForEmptyArray(): void
    {
        $this->assertSame('', $this->createField(value: [])->getValueAsString());
    }

    public function testGetValueAsStringMatchesRenderValueWithoutEncoding(): void
    {
        $field = $this->createField(value: ['a', 'b']);

        $this->assertSame($field->renderValue(), $field->getValueAsString());
    }

    public function testGetValueAsStringIsEmptyAfterValidationWithMissingKey(): void
    {
        $field = $this->createField(value: 'a');

        $field->validate(inputData: []);

        $this->assertSame('', $field->getValueAsString());
    }

    public function testGetValueAsStringReturnsPostedString(): void
    {
        $field = $this->createField();

        $field->validate(inputData: ['text' => " a\nb "]);

        $this->assertSame(" a\nb ", $field->getValueAsString());
    }

    public function testGetValueAsStringReturnsPreviousValueAfterRejectedArrayInput(): void
    {
        $field = $this->createField(value: 'a');

        $field->validate(inputData: ['text' => ['x']]);

        $this->assertSame('a', $field->getValueAsString());
    }

    public function testGetValueAsStringIsEmptyAfterValidationWithMissingKeyOnArrayField(): void
    {
        $field = $this->createField(value: ['a']);

        $field->validate(inputData: []);

        $this->assertSame('', $field->getValueAsString());
    }

    public function testGetValueAsStringThrowsForNestedArrayEntry(): void
    {
        $field = $this->createField(value: ['a']);
        $field->validate(inputData: ['text' => ['a', ['b']]]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('field text');
        $this->expectExceptionMessage('array');

        $field->getValueAsString();
    }

    public function testGetValuesIsEmptyForNull(): void
    {
        $this->assertSame([], $this->createField()->getValues());
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function textProvider(): array
    {
        return [
            'lf' => ["a\nb", ['a', 'b']],
            'crlf' => ["a\r\nb", ['a', 'b']],
            'cr' => ["a\rb", ['a', 'b']],
            'mixed line breaks' => ["a\r\nb\nc\rd", ['a', 'b', 'c', 'd']],
            'blank lines' => ["a\n\n\r\n  \nb\n", ['a', 'b']],
            'surrounding whitespace' => ["  a \n\tb\t", ['a', 'b']],
            'inner whitespace is kept' => ['a  b', ['a  b']],
            'single line' => ['a', ['a']],
            'empty string' => ['', []],
            'blank text' => [" \n \r\n", []],
            'zero line is kept' => ["0\n1", ['0', '1']],
            'multibyte characters are not split' => ["\u{C5}\n\u{2026}\u{85}", ['Å', "\u{2026}\u{85}"]],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('textProvider')]
    public function testGetValuesSplitsStringIntoTrimmedNonEmptyLines(string $text, array $expected): void
    {
        $this->assertSame($expected, $this->createField(value: $text)->getValues());
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('textProvider')]
    public function testGetValuesSplitsPostedStringIntoLines(string $text, array $expected): void
    {
        $field = $this->createField();
        $field->validate(inputData: ['text' => $text]);

        $this->assertSame($expected, $field->getValues());
    }

    public function testGetValuesIsEmptyAfterValidationWithMissingKey(): void
    {
        $field = $this->createField(value: "a\nb");
        $field->validate(inputData: []);

        $this->assertSame([], $field->getValues());
    }

    public function testGetValuesReturnsArrayValueAsList(): void
    {
        $this->assertSame(['a', 'b'], $this->createField(value: ['a', 'b'])->getValues());
    }

    public function testGetValuesNormalizesArrayValueLikeString(): void
    {
        $field = $this->createField(value: [' a ', '', "  ", "b\r\nc", '0']);

        $this->assertSame(['a', 'b', 'c', '0'], $field->getValues());
    }

    public function testGetValuesReindexesPostedArray(): void
    {
        $field = $this->createField(value: ['x']);
        $field->validate(inputData: ['text' => [3 => 'b', 1 => 'a']]);

        $this->assertSame(['b', 'a'], $field->getValues());
    }

    public function testGetValuesReturnsPreviousValueAfterRejectedArrayInput(): void
    {
        $field = $this->createField(value: "a\nb");

        $this->assertFalse($field->validate(inputData: ['text' => ['c']]));
        $this->assertSame(['a', 'b'], $field->getValues());
    }

    public function testGetValuesStringReplacesArrayValue(): void
    {
        $field = $this->createField(value: ['a']);
        $field->validate(inputData: ['text' => "b\nc"]);

        $this->assertSame(['b', 'c'], $field->getValues());
    }

    public function testGetValuesThrowsForNestedArrayEntry(): void
    {
        $field = $this->createField(value: ['a']);
        $field->validate(inputData: ['text' => ['a', ['b']]]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('field text');
        $this->expectExceptionMessage('array');

        $field->getValues();
    }

    /**
     * @return array<string, array{null|string|array<mixed>}>
     */
    public static function validInputProvider(): array
    {
        return [
            'string' => ["a\nb"],
            'empty string' => [''],
            'missing key' => [null],
            'array (array field)' => [['a', 'b']],
            'empty array (array field)' => [[]],
        ];
    }

    /**
     * @param null|string|array<mixed> $input
     */
    #[DataProvider('validInputProvider')]
    public function testGetValuesNeverThrowsAfterSuccessfulValidation(null|string|array $input): void
    {
        $field = $this->createField(value: is_array($input) ? ['x'] : null);
        $inputData = $input === null ? [] : ['text' => $input];

        $this->assertTrue($field->validate(inputData: $inputData));
        // Must not throw: a validated field is always readable
        $field->getValues();
    }
}