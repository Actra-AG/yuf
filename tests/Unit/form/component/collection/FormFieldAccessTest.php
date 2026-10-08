<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\collection;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\form\FormContextFactory;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Access to the fields of a form: `hasField()`, `getField()`, `removeField()` and `getAllFields()`.
 */
final class FormFieldAccessTest extends TestCase
{
    private function createForm(): Form
    {
        $form = new Form(context: FormContextFactory::create(), name: 'access');
        $form->removeCsrfProtection();
        $form->addField(formField: new TextField(name: 'name', label: HtmlText::fromText(text: 'Name')));
        $form->addComponent(
            formComponent: new FormControl(name: 'send', submitLabel: HtmlText::fromText(text: 'Send')),
        );

        return $form;
    }

    public function testAFieldCanBeFoundAndRemoved(): void
    {
        $form = $this->createForm();

        $this->assertTrue($form->hasField(name: 'name'));
        $this->assertSame('name', $form->getField(name: 'name')->name);

        $form->removeField(name: 'name');

        $this->assertFalse($form->hasField(name: 'name'));
        $this->assertSame([], $form->getAllFields());
    }

    public function testAComponentThatIsNoFieldIsNotAField(): void
    {
        $form = $this->createForm();

        $this->assertFalse($form->hasField(name: 'send'));
        $this->assertFalse($form->hasField(name: 'missing'));
    }

    public function testGetFieldRejectsAComponentThatIsNoField(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The component send of form access is not a FormField.');

        $this->createForm()->getField(name: 'send');
    }

    public function testGetFieldRejectsAMissingComponent(): void
    {
        $this->expectException(LogicException::class);

        $this->createForm()->getField(name: 'missing');
    }

    public function testRemoveFieldRejectsAComponentThatIsNoFieldAndKeepsIt(): void
    {
        $form = $this->createForm();

        try {
            $form->removeField(name: 'send');
            FormFieldAccessTest::fail('A LogicException was expected.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('has no field send', $exception->getMessage());
        }

        $this->assertTrue($form->hasChildComponent(childComponentName: 'send'));
    }

    public function testRemoveFieldRejectsAMissingField(): void
    {
        $this->expectException(LogicException::class);

        $this->createForm()->removeField(name: 'missing');
    }
}
