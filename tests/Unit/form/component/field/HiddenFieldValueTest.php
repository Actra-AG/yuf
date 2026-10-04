<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\HiddenField;
use actra\yuf\form\FormInput;
use PHPUnit\Framework\TestCase;

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

        $field->validate(input: FormInput::fromArray(data: ['hidden' => ' a ']));

        $this->assertSame(' a ', $field->getValueAsString());
    }

    public function testConstructorValueIsNotTrimmed(): void
    {
        $this->assertSame(' a ', new HiddenField(name: 'hidden', value: ' a ')->getValueAsString());
    }

    public function testZeroWidthSpacesAreRemoved(): void
    {
        $field = new HiddenField(name: 'hidden');

        $field->validate(input: FormInput::fromArray(data: ['hidden' => "a\u{200B}b"]));

        $this->assertSame('ab', $field->getValueAsString());
    }

    public function testValueIsEmptyStringAfterValidationWithMissingKey(): void
    {
        $field = new HiddenField(name: 'hidden', value: '5');

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: [])));

        $this->assertSame('', $field->getValueAsString());
    }

    public function testArrayInputIsRejectedAndResetsValue(): void
    {
        $field = new HiddenField(name: 'hidden', value: '5');

        $isValid = $field->validate(input: FormInput::fromArray(data: ['hidden' => ['x']]));

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
}