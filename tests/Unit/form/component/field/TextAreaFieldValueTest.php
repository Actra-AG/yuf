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
use TypeError;

/**
 * The string value of TextAreaField and `getValues()` (one entry per line).
 */
final class TextAreaFieldValueTest extends TestCase
{
    private function createField(?string $value = null): TextAreaField
    {
        return new TextAreaField(
            name: 'text',
            label: HtmlText::encoded(textContent: 'Text'),
            value: $value
        );
    }

    public function testValueIsEmptyStringAfterConstructionWithoutValue(): void
    {
        $this->assertSame('', $this->createField()->getValueAsString());
    }

    public function testValueIsStringAfterConstructionWithString(): void
    {
        $this->assertSame("a\nb", $this->createField(value: "a\nb")->getValueAsString());
    }

    public function testStringInputIsNotTrimmedAndLosesZeroWidthSpaces(): void
    {
        $field = $this->createField();

        $field->validate(inputData: ['text' => " a\u{200B}\nb "]);

        $this->assertSame(" a\nb ", $field->getValueAsString());
    }

    public function testConstructorValueIsNotTrimmed(): void
    {
        $this->assertSame("  indented\n", $this->createField(value: "  indented\n")->getValueAsString());
    }

    public function testArrayInputResetsValueAndAddsOneError(): void
    {
        $field = $this->createField(value: 'initial');

        $isValid = $field->validate(inputData: ['text' => ['x', 'y']]);

        $this->assertFalse($isValid);
        $this->assertSame('', $field->getValueAsString());
        $this->assertSame(1, $field->errorCollection->count());
    }

    public function testValueIsEmptyStringAfterValidationWithMissingKey(): void
    {
        $field = $this->createField(value: 'initial');

        $this->assertTrue($field->validate(inputData: []));

        $this->assertSame('', $field->getValueAsString());
    }

    public function testSetValueChangesCurrentValueOnly(): void
    {
        $field = $this->createField(value: 'initial');

        $field->setValue(value: "new\nvalue");

        $this->assertSame("new\nvalue", $field->getValueAsString());
        $this->assertSame('initial', $field->getOriginalValue());
        $this->assertTrue($field->valueHasChanged());
    }

    public function testSetValueRejectsArray(): void
    {
        $this->expectException(TypeError::class);

        $this->createField()->setValue(value: ['a', 'b']);
    }

    public function testRenderValueEncodesTheText(): void
    {
        $this->assertSame("a&lt;\nb", $this->createField(value: "a<\nb")->renderValue());
    }

    public function testRenderValueOfEmptyFieldIsEmptyString(): void
    {
        $this->assertSame('', $this->createField()->renderValue());
    }

    public function testBlankTextIsEmpty(): void
    {
        $this->assertTrue($this->createField(value: " \n ")->isValueEmpty());
    }

    public function testZeroIsNotEmpty(): void
    {
        $this->assertFalse($this->createField(value: '0')->isValueEmpty());
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

    public function testGetValuesIsEmptyAfterRejectedArrayInput(): void
    {
        $field = $this->createField(value: "a\nb");

        $this->assertFalse($field->validate(inputData: ['text' => ['c']]));
        $this->assertSame([], $field->getValues());
    }
}