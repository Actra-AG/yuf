<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\TextAreaField;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;

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
}