<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\TextField;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\form\FormContextFactory;
use LogicException;
use PHPUnit\Framework\TestCase;

final class FormFieldTopFormComponentTest extends TestCase
{
    private function createField(): TextField
    {
        return new TextField(name: 'name', label: HtmlText::fromHtml(html: 'Name'));
    }

    public function testFieldWithoutFormHasNoTopFormComponent(): void
    {
        $this->assertFalse($this->createField()->hasTopFormComponent());
    }

    public function testReadingTheTopFormComponentWithoutFormThrows(): void
    {
        $field = $this->createField();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The field name is not part of a form yet. Add it with Form::addField().');

        $field->topFormComponent; // @phpstan-ignore expr.resultUnused (the read throws)
    }

    public function testFormAddsItselfAsTopFormComponent(): void
    {
        $form = new Form(context: FormContextFactory::create(), name: 'topFormComponentForm');
        $field = $this->createField();

        $form->addField(formField: $field);

        $this->assertTrue($field->hasTopFormComponent());
        $this->assertSame($form, $field->topFormComponent);
    }
}
