<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\BooleanField;
use actra\yuf\form\component\field\DecimalField;
use actra\yuf\form\component\field\IntegerField;
use actra\yuf\form\component\field\MultiSelectOptionsField;
use actra\yuf\form\component\field\PasswordField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\form\FormNameRegistry;
use actra\yuf\form\FormOptions;
use actra\yuf\form\rule\IntegerMinRule;
use actra\yuf\form\rule\MinLengthRule;
use actra\yuf\form\settings\PasswordPurposeEnum;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\form\NoSpacesRule;
use actra\yuf\tests\Double\security\InMemoryCsrfTokenSource;
use PHPUnit\Framework\TestCase;

/**
 * The code of the "Forms" section of README.md, with the request passed as `FormInput` instead of the superglobals.
 * Keep both in sync.
 */
final class ReadmeExamplesTest extends TestCase
{
    protected function setUp(): void
    {
        FormNameRegistry::reset();
    }

    public function testFormExample(): void
    {
        $requiredError = HtmlText::encoded(textContent: 'Required');
        $form = new Form(
            name: 'order',
            messages: FormMessages::german(),
            csrfTokenSource: new InMemoryCsrfTokenSource(token: 'token'),
        );
        $name = new TextField(
            name: 'customer',
            label: HtmlText::encoded(textContent: 'Name'),
            requiredError: $requiredError,
        );
        $quantity = new IntegerField(name: 'quantity', label: HtmlText::encoded(textContent: 'Quantity'));
        $form->addField(formField: $name);
        $form->addField(formField: $quantity);

        $isValid = $form->validate(
            input: FormInput::fromArray(
                data: ['customer' => 'Ann', 'quantity' => '2', 'csrftoken' => 'token'],
                query: ['order' => ''],
            ),
        );

        $this->assertTrue($isValid);
        $this->assertSame('Ann', $name->getValueAsString());
        $this->assertSame(2, $quantity->getValueAsInt());
        $this->assertStringContainsString('name="customer"', $form->render());
    }

    public function testFieldConstructorExamples(): void
    {
        $requiredError = HtmlText::encoded(textContent: 'Required');
        $label = HtmlText::encoded(textContent: 'Label');
        $options = new FormOptions();
        $options->addItem(key: 'a', htmlText: HtmlText::encoded(textContent: 'A'));

        $password = new PasswordField(
            name: 'password',
            label: HtmlText::encoded(textContent: 'Password'),
            requiredError: $requiredError,
            purpose: PasswordPurposeEnum::NEW,
        );
        $price = new DecimalField(
            name: 'price',
            label: HtmlText::encoded(textContent: 'Price'),
            scale: 2,
            initialValue: '12.50',
        );
        $agree = new BooleanField(
            name: 'agree',
            label: HtmlText::encoded(textContent: 'I agree'),
            isCheckedByDefault: false,
        );
        $tags = new MultiSelectOptionsField(name: 'tags', label: $label, formOptions: $options, initialValues: ['a']);

        $this->assertStringContainsString('autocomplete="new-password"', $password->getHtmlTag()->render());
        $this->assertSame('12.50', $price->getValueAsDecimal());
        $this->assertFalse($agree->isChecked());
        $this->assertSame(['a'], $tags->getValues());
    }

    public function testRulesExample(): void
    {
        $tooShort = HtmlText::encoded(textContent: 'Too short');
        $noSpaces = HtmlText::encoded(textContent: 'No spaces');
        $atLeastOne = HtmlText::encoded(textContent: 'At least one');
        $name = new TextField(name: 'customer', label: HtmlText::encoded(textContent: 'Name'));
        $quantity = new IntegerField(name: 'quantity', label: HtmlText::encoded(textContent: 'Quantity'));

        $name->addRule(formRule: new MinLengthRule(minLength: 3, errorMessage: $tooShort));
        $name->addRule(formRule: new NoSpacesRule(defaultErrorMessage: $noSpaces));
        $quantity->addValueRule(formRule: new IntegerMinRule(min: 1, errorMessage: $atLeastOne));

        $this->assertFalse($name->validate(input: FormInput::fromArray(data: ['customer' => 'A b'])));
        $this->assertFalse($quantity->validate(input: FormInput::fromArray(data: ['quantity' => '0'])));
        $validName = new TextField(name: 'customer', label: HtmlText::encoded(textContent: 'Name'));
        $validName->addRule(formRule: new MinLengthRule(minLength: 3, errorMessage: $tooShort));
        $validName->addRule(formRule: new NoSpacesRule(defaultErrorMessage: $noSpaces));
        $this->assertTrue($validName->validate(input: FormInput::fromArray(data: ['customer' => 'Ann'])));
    }

    public function testRequestDataExample(): void
    {
        $form = new Form(name: 'order', csrfTokenSource: new InMemoryCsrfTokenSource(token: 'token'));
        $form->addField(
            formField: new TextField(name: 'customer', label: HtmlText::encoded(textContent: 'Name')),
        );
        $form->addField(
            formField: new IntegerField(name: 'quantity', label: HtmlText::encoded(textContent: 'Quantity')),
        );

        $input = FormInput::fromArray(
            data: ['customer' => 'Ann', 'quantity' => '2', 'csrftoken' => 'token'],
            query: ['order' => ''],
        );
        $isValid = $form->validate(input: $input);

        $this->assertTrue($isValid);
    }
}
