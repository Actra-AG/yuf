<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\collection;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\form\FormNameRegistry;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;

/**
 * `Form(messages:)` hands its texts to the fields in `addField()`.
 */
final class FormMessagesHandoverTest extends TestCase
{
    private function createField(): TextField
    {
        return new TextField(name: 'field', label: HtmlText::encoded(textContent: 'Label'));
    }

    protected function setUp(): void
    {
        FormNameRegistry::reset();
    }

    public function testFieldWithoutFormUsesEnglishDefaults(): void
    {
        $this->assertSame('The invalid input was ignored.', $this->createField()->messages->invalidInput);
    }

    public function testFormWithoutMessagesGivesEnglishDefaults(): void
    {
        $form = new Form(name: 'handoverDefaultForm');
        $field = $this->createField();

        $form->addField(formField: $field);

        $this->assertSame('The invalid input was ignored.', $field->messages->invalidInput);
    }

    public function testFieldGetsTheMessagesOfTheForm(): void
    {
        $form = new Form(name: 'handoverGermanForm', messages: FormMessages::german());
        $field = $this->createField();

        $form->addField(formField: $field);
        $field->validate(input: FormInput::fromArray(data: ['field' => ['x']]));

        $this->assertSame(
            'Die ungültige Eingabe wurde ignoriert.',
            $field->errorCollection->getFirstError()->render()
        );
    }

    public function testCustomMessagesAreUsed(): void
    {
        $form = new Form(name: 'handoverCustomForm', messages: new FormMessages(invalidInput: 'Nope.'));
        $field = $this->createField();

        $form->addField(formField: $field);

        $this->assertSame('Nope.', $field->messages->invalidInput);
    }
}