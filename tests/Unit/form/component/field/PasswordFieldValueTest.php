<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\PasswordField;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;

final class PasswordFieldValueTest extends TestCase
{
    private function createField(): PasswordField
    {
        return new PasswordField(
            name: 'password',
            label: HtmlText::encoded(textContent: 'Password'),
            requiredError: HtmlText::encoded(textContent: 'Required')
        );
    }

    public function testValueIsEmptyStringAfterConstruction(): void
    {
        $this->assertSame('', $this->createField()->getRawValue());
    }

    public function testStringInputIsStoredUntrimmed(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['password' => ' secret ']);

        $this->assertTrue($isValid);
        $this->assertSame(' secret ', $field->getRawValue());
    }

    public function testValueIsNullAfterValidationWithMissingKey(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: []);

        $this->assertFalse($isValid);
        $this->assertNull($field->getRawValue());
    }

    public function testArrayInputIsRejectedAndKeepsEmptyString(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['password' => ['x']]);

        $this->assertFalse($isValid);
        $this->assertSame('', $field->getRawValue());
    }
}