<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\tests\Double\form\InitialValueTextAreaField;
use actra\yuf\tests\Double\form\InitialValueTextField;
use actra\yuf\html\HtmlText;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * The protected setInitialValue() of the text fields: sets the current and the initial value, but only before the
 * field has been validated.
 */
final class InitialValueTest extends TestCase
{
    private function createTextField(): InitialValueTextField
    {
        return new InitialValueTextField(name: 'field', label: HtmlText::encoded(textContent: 'Label'));
    }

    private function createTextAreaField(): InitialValueTextAreaField
    {
        return new InitialValueTextAreaField(name: 'field', label: HtmlText::encoded(textContent: 'Label'));
    }

    public function testSetInitialValueSetsCurrentAndInitialValue(): void
    {
        $field = $this->createTextField();

        $field->fill(value: 'from database');

        $this->assertSame('from database', $field->getValueAsString());
        $this->assertSame('from database', $field->getOriginalValue());
        $this->assertFalse($field->valueHasChanged());
    }

    public function testLastCallWinsBeforeValidation(): void
    {
        $field = $this->createTextField();

        $field->fill(value: 'first');
        $field->fill(value: 'second');

        $this->assertSame('second', $field->getValueAsString());
        $this->assertFalse($field->valueHasChanged());
    }

    public function testSetInitialValueAfterValidateThrows(): void
    {
        $field = $this->createTextField();
        $field->validate(inputData: []);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('field');

        $field->fill(value: 'late');
    }

    public function testSetInitialValueAfterValidateCurrentValueThrows(): void
    {
        $field = $this->createTextField();
        $field->validateCurrentValue();

        $this->expectException(LogicException::class);

        $field->fill(value: 'late');
    }

    public function testSetInitialValueAfterValidateWithoutOverwriteThrows(): void
    {
        $field = $this->createTextField();
        $field->validate(inputData: [], overwriteValue: false);

        $this->expectException(LogicException::class);

        $field->fill(value: 'late');
    }

    public function testSetInitialValueOfTextAreaAfterValidateThrows(): void
    {
        $field = $this->createTextAreaField();
        $field->fill(value: 'ok');
        $field->validate(inputData: []);

        $this->expectException(LogicException::class);

        $field->fill(value: 'late');
    }

    public function testPublicSetterStillWorksAfterValidate(): void
    {
        $field = $this->createTextField();
        $field->fill(value: 'initial');
        $field->validate(inputData: ['field' => 'posted']);

        $field->setValue(value: 'later');

        $this->assertSame('later', $field->getValueAsString());
        $this->assertSame('initial', $field->getOriginalValue());
    }
}