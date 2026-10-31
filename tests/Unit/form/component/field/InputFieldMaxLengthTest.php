<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\EmailField;
use actra\yuf\form\component\field\IntegerField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\form\rule\MaxLengthRule;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\form\FormContextFactory;
use PHPUnit\Framework\TestCase;

/**
 * `maxLength` of an input field is validated on the server, not only rendered as HTML attribute.
 */
final class InputFieldMaxLengthTest extends TestCase
{
    private function createField(?int $maxLength = 5): TextField
    {
        return new TextField(name: 'name', label: HtmlText::fromHtml(html: 'Name'), maxLength: $maxLength);
    }

    public function testTextLongerThanMaxLengthIsInvalid(): void
    {
        $field = $this->createField();

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['name' => 'abcdef'])));
        $this->assertSame(
            'The text must not be longer than 5 characters.',
            $field->errorCollection->getFirstError()->render(),
        );
    }

    public function testTextOfMaxLengthIsValid(): void
    {
        $field = $this->createField();

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['name' => 'abcde'])));
    }

    public function testLengthIsCountedInCharacters(): void
    {
        $field = $this->createField();

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['name' => 'äöüéè'])));
    }

    public function testFieldWithoutMaxLengthAcceptsLongText(): void
    {
        $field = $this->createField(maxLength: null);

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['name' => str_repeat('a', 5000)])));
    }

    public function testMessageComesFromTheFormMessages(): void
    {
        $form = new Form(
            context: FormContextFactory::create(),
            name: 'maxLengthForm',
            messages: FormMessages::german(),
            csrfProtection: false,
        );
        $field = $this->createField();
        $form->addField(formField: $field);

        $field->validate(input: FormInput::fromArray(data: ['name' => 'abcdef']));

        $this->assertSame(
            'Der Text darf höchstens 5 Zeichen lang sein.',
            $field->errorCollection->getFirstError()->render(),
        );
    }

    public function testOwnMaxLengthRuleWithTheSameLimitGivesOnlyOneError(): void
    {
        $field = $this->createField();
        $field->addRule(
            formRule: new MaxLengthRule(maxLength: 5, errorMessage: HtmlText::fromHtml(html: 'Own message')),
        );

        $field->validate(input: FormInput::fromArray(data: ['name' => 'abcdef']));

        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('Own message', $field->errorCollection->getFirstError()->render());
    }

    public function testNumberAndEmailFieldsValidateMaxLengthToo(): void
    {
        $integerField = new IntegerField(name: 'qty', label: HtmlText::fromHtml(html: 'Qty'), maxLength: 2);
        $emailField = new EmailField(
            name: 'mail',
            label: HtmlText::fromHtml(html: 'Mail'),
            value: null,
            invalidError: HtmlText::fromHtml(html: 'Invalid'),
            mailDomainResolver: null,
            maxLength: 10,
        );

        $this->assertFalse($integerField->validate(input: FormInput::fromArray(data: ['qty' => '123'])));
        $this->assertFalse(
            $emailField->validate(input: FormInput::fromArray(data: ['mail' => 'abcdefgh@example.com'])),
        );
    }
}
