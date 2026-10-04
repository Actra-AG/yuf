<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\TextField;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of the value handling of FormField (base class) and TextField.
 */
final class TextFieldValueTest extends TestCase
{
    private function createField(?string $value = null): TextField
    {
        return new TextField(
            name: 'field',
            label: HtmlText::encoded(textContent: 'Label'),
            value: $value
        );
    }

    public function testValueIsNullAfterConstructionWithoutValue(): void
    {
        $this->assertNull($this->createField()->getRawValue());
    }

    public function testValueIsStringAfterConstructionWithString(): void
    {
        $this->assertSame('initial', $this->createField(value: 'initial')->getRawValue());
    }

    public function testStringInputIsStoredUntrimmed(): void
    {
        $field = $this->createField();

        $field->validate(inputData: ['field' => '  text  ']);

        $this->assertSame('  text  ', $field->getRawValue());
    }

    public function testZeroWidthSpacesAreRemovedFromStringInput(): void
    {
        $field = $this->createField();

        $field->validate(inputData: ['field' => "a\u{200B}b\u{200B}"]);

        $this->assertSame('ab', $field->getRawValue());
    }

    public function testZeroWidthSpacesAreRemovedFromConstructorValueButNotFromOriginalValue(): void
    {
        $field = $this->createField(value: "a\u{200B}");

        $this->assertSame('a', $field->getRawValue());
        $this->assertSame("a\u{200B}", $field->getOriginalValue());
    }

    public function testValueIsNullAfterValidationWithMissingKey(): void
    {
        $field = $this->createField(value: 'initial');

        $field->validate(inputData: []);

        $this->assertNull($field->getRawValue());
    }

    public function testArrayInputIsRejectedAndKeepsPreviousValue(): void
    {
        $field = $this->createField(value: 'initial');

        $isValid = $field->validate(inputData: ['field' => ['x']]);

        $this->assertFalse($isValid);
        $this->assertSame('initial', $field->getRawValue());
    }

    public function testArrayInputIsRejectedAndKeepsNullWithoutPreviousValue(): void
    {
        $field = $this->createField();

        $field->validate(inputData: ['field' => ['x']]);

        $this->assertNull($field->getRawValue());
    }

    public function testValueIsNotOverwrittenWhenOverwriteValueIsFalse(): void
    {
        $field = $this->createField(value: 'initial');

        $field->validate(inputData: ['field' => 'other'], overwriteValue: false);

        $this->assertSame('initial', $field->getRawValue());
    }

    /**
     * @return iterable<string, array{?string, bool, ?string}>
     */
    public static function returnNullIfEmptyProvider(): iterable
    {
        yield 'null value' => [null, true, null];
        yield 'empty string returns null' => ['', true, null];
        yield 'whitespace only returns null' => ['  ', true, null];
        yield 'filled string is returned' => ['x', true, 'x'];
        yield 'empty string without flag is returned' => ['', false, ''];
        yield 'null without flag is returned' => [null, false, null];
    }

    #[DataProvider('returnNullIfEmptyProvider')]
    public function testGetRawValueReturnNullIfEmpty(?string $value, bool $returnNullIfEmpty, ?string $expected): void
    {
        $field = $this->createField(value: $value);

        $this->assertSame($expected, $field->getRawValue(returnNullIfEmpty: $returnNullIfEmpty));
    }

    public function testSetValueWithArrayIsRejectedAndAddsError(): void
    {
        $field = $this->createField(value: 'initial');

        $field->setValue(value: ['x']);

        $this->assertSame('initial', $field->getRawValue());
        $this->assertTrue($field->hasErrors(withChildElements: false));
    }

    public function testRenderValueEncodesValue(): void
    {
        $field = $this->createField(value: '<b>"x"</b>');

        $this->assertSame('&lt;b&gt;&quot;x&quot;&lt;/b&gt;', $field->renderValue());
    }
}