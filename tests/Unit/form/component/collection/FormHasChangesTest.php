<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\collection;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\BooleanField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\field\ToggleField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\form\FormContextFactory;
use actra\yuf\tests\Double\security\InMemoryCsrfTokenSource;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * `Form::hasChanges()`: true if the current value of any field differs from its initial value.
 */
final class FormHasChangesTest extends TestCase
{
    private TextField $name;
    private BooleanField $agree;

    #[Override]
    protected function setUp(): void
    {
        $this->name = new TextField(name: 'name', label: HtmlText::fromHtml(html: 'Name'), value: 'Ann');
        $this->agree = new BooleanField(
            name: 'agree',
            label: HtmlText::fromHtml(html: 'Agree'),
            isCheckedByDefault: false,
        );
    }

    private function createForm(bool $withCsrf = true): Form
    {
        $form = new Form(
            context: FormContextFactory::create(
                csrfTokenSource: $withCsrf ? new InMemoryCsrfTokenSource(token: 'tok') : null,
            ),
            name: 'contact',
        );
        $form->addField(formField: $this->name);
        $form->addField(formField: $this->agree);

        return $form;
    }

    public function testNothingChangedBeforeInput(): void
    {
        $this->assertFalse($this->createForm()->hasChanges());
    }

    public function testFormWithoutFieldsHasNoChanges(): void
    {
        $form = new Form(context: FormContextFactory::create(), name: 'empty');

        $this->assertFalse($form->hasChanges());
    }

    public function testPostedInitialValuesAndTheCsrfTokenAreNoChange(): void
    {
        $form = $this->createForm();

        $form->validate(
            input: FormInput::fromArray(data: ['name' => 'Ann', 'csrftoken' => 'tok'], query: ['contact' => '']),
        );

        $this->assertFalse($form->hasChanges());
    }

    public function testAChangedTextIsAChange(): void
    {
        $form = $this->createForm();

        $form->validate(
            input: FormInput::fromArray(data: ['name' => 'Bob', 'csrftoken' => 'tok'], query: ['contact' => '']),
        );

        $this->assertTrue($form->hasChanges());
    }

    public function testACheckedBoxIsAChange(): void
    {
        $form = $this->createForm(withCsrf: false);

        $this->agree->setChecked(checked: true);

        $this->assertTrue($form->hasChanges());
    }

    public function testAChangedSetterValueIsAChange(): void
    {
        $form = $this->createForm();

        $this->name->setValue(value: 'Cy');

        $this->assertTrue($form->hasChanges());
    }

    public function testAChangedChildFieldOfAToggleFieldIsAChange(): void
    {
        $form = $this->createForm();
        $options = new FormOptions();
        $options->addItem(key: 'a', htmlText: HtmlText::fromHtml(html: 'A'));
        $toggle = new ToggleField(
            name: 'toggle',
            label: HtmlText::fromHtml(html: 'Toggle'),
            formOptions: $options,
            initialValue: 'a',
        );
        $child = new TextField(name: 'child', label: HtmlText::fromHtml(html: 'Child'));
        $toggle->addChildField(mainOption: 'a', childField: $child);
        $form->addField(formField: $toggle);
        $this->assertFalse($form->hasChanges());

        $child->setValue(value: 'x');

        $this->assertTrue($form->hasChanges());
    }
}
