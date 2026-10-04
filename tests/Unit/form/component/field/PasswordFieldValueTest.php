<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\PasswordField;
use actra\yuf\form\FormInput;
use actra\yuf\form\settings\AutoCompleteValue;
use actra\yuf\form\settings\PasswordPurposeEnum;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PasswordFieldValueTest extends TestCase
{
    private function createField(PasswordPurposeEnum $purpose = PasswordPurposeEnum::CURRENT): PasswordField
    {
        return new PasswordField(
            name: 'password',
            label: HtmlText::encoded(textContent: 'Password'),
            requiredError: HtmlText::encoded(textContent: 'Required'),
            purpose: $purpose
        );
    }

    public function testValueIsEmptyStringAfterConstruction(): void
    {
        $this->assertSame('', $this->createField()->getValueAsString());
    }

    public function testPasswordIsNotNormalized(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(input: FormInput::fromArray(data: ['password' => " se\u{200B}cret "]));

        $this->assertTrue($isValid);
        $this->assertSame(" se\u{200B}cret ", $field->getValueAsString());
    }

    public function testValueIsEmptyStringAfterValidationWithMissingKey(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(input: FormInput::fromArray(data: []));

        $this->assertFalse($isValid);
        $this->assertSame('', $field->getValueAsString());
    }

    public function testArrayInputIsRejectedWithOneError(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(input: FormInput::fromArray(data: ['password' => ['x']]));

        $this->assertFalse($isValid);
        $this->assertSame('', $field->getValueAsString());
        $this->assertSame(1, $field->errorCollection->count());
    }

    public function testPostedPasswordIsNotRendered(): void
    {
        $field = $this->createField();

        $field->validate(input: FormInput::fromArray(data: ['password' => 'secret']));

        $this->assertSame('', $field->renderValue());
        $this->assertSame('secret', $field->getValueAsString());
    }

    public function testPostedPasswordIsNotInRenderedHtml(): void
    {
        $field = $this->createField();

        $field->validate(input: FormInput::fromArray(data: ['password' => 'secret']));

        $this->assertStringNotContainsString('secret', (string)$field->getHtmlTag()?->render());
    }

    public function testFieldHasNoSetter(): void
    {
        $this->assertFalse(new ReflectionClass(objectOrClass: PasswordField::class)->hasMethod(name: 'setValue'));
    }

    /**
     * @return iterable<string, array{PasswordPurposeEnum, string}>
     */
    public static function purposeProvider(): iterable
    {
        yield 'current password' => [PasswordPurposeEnum::CURRENT, 'current-password'];
        yield 'new password' => [PasswordPurposeEnum::NEW, 'new-password'];
    }

    #[DataProvider('purposeProvider')]
    public function testRendersAutocompleteAttributeOfThePurpose(PasswordPurposeEnum $purpose, string $expected): void
    {
        $field = $this->createField(purpose: $purpose);

        $this->assertSame($expected, $field->autoComplete?->value);
        $this->assertStringContainsString('autocomplete="' . $expected . '"', (string)$field->getHtmlTag()?->render());
    }

    public function testAutocompleteEnumValuesAreTheOnesOfThePurposes(): void
    {
        $this->assertSame(AutoCompleteValue::CURRENT_PASSWORD, $this->createField()->autoComplete);
    }
}