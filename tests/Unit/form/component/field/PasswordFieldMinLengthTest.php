<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\PasswordField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\form\settings\PasswordPurposeEnum;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\form\FormContextFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PasswordFieldMinLengthTest extends TestCase
{
    private function createField(): PasswordField
    {
        return new PasswordField(
            name: 'password',
            label: HtmlText::fromHtml(html: 'Password'),
            requiredError: HtmlText::fromHtml(html: 'Required'),
            purpose: PasswordPurposeEnum::NEW,
        );
    }

    public function testWithoutMinimumLengthAShortPasswordIsValid(): void
    {
        $field = $this->createField();

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['password' => 'a'])));
    }

    public function testPasswordOfTheMinimumLengthIsValid(): void
    {
        $field = $this->createField();
        $field->setMinLength(minLength: 8);

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['password' => '12345678'])));
    }

    public function testShortPasswordGetsTheDefaultEnglishMessage(): void
    {
        $field = $this->createField();
        $field->setMinLength(minLength: 8);

        $isValid = $field->validate(input: FormInput::fromArray(data: ['password' => '1234567']));

        $this->assertFalse($isValid);
        $this->assertSame(
            'The password must have at least 8 characters.',
            $field->errorCollection->getFirstError()->render(),
        );
    }

    public function testMessageIsTakenFromTheMessagesOfTheForm(): void
    {
        $form = new Form(context: FormContextFactory::create(), name: 'set', messages: FormMessages::german());
        $field = $this->createField();
        $field->setMinLength(minLength: 12);
        $form->addField(formField: $field);

        $form->validate(input: FormInput::fromArray(data: ['password' => 'kurz'], query: ['set' => '']));

        $this->assertSame(
            'Das Passwort muss mindestens 12 Zeichen lang sein.',
            $field->errorCollection->getFirstError()->render(),
        );
    }

    public function testOwnMessageReplacesTheDefault(): void
    {
        $field = $this->createField();
        $field->setMinLength(minLength: 8, errorMessage: HtmlText::fromHtml(html: 'Too short'));

        $field->validate(input: FormInput::fromArray(data: ['password' => 'abc']));

        $this->assertSame('Too short', $field->errorCollection->getFirstError()->render());
    }

    public function testLengthCountsCharactersAndSpacesAreNotTrimmed(): void
    {
        $field = $this->createField();
        $field->setMinLength(minLength: 4);

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['password' => 'äöü '])));
    }

    public function testEmptyPasswordOnlyFailsTheRequiredRule(): void
    {
        $field = $this->createField();
        $field->setMinLength(minLength: 8);

        $field->validate(input: FormInput::fromArray(data: ['password' => '']));

        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('Required', $field->errorCollection->getFirstError()->render());
    }

    public function testInvalidMinimumLengthThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createField()->setMinLength(minLength: 0);
    }
}
