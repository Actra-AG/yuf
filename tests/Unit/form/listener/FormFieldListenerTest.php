<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\listener;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormNameRegistry;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\form\RecordingFieldListener;
use actra\yuf\tests\Double\security\InMemoryCsrfTokenSource;
use PHPUnit\Framework\TestCase;

/**
 * The listeners of a field run around the required check and the rules, in the order of the v3 `validate()`.
 */
final class FormFieldListenerTest extends TestCase
{
    protected function setUp(): void
    {
        FormNameRegistry::reset();
    }

    private function createField(RecordingFieldListener $listener, bool $required = false): TextField
    {
        $form = new Form(name: 'listenerForm', csrfTokenSource: new InMemoryCsrfTokenSource(token: 'tok'));
        $field = new TextField(
            name: 'field',
            label: HtmlText::encoded(textContent: 'Field'),
            requiredError: $required ? HtmlText::encoded(textContent: 'Required') : null
        );
        $form->addField(formField: $field);
        $field->addListener(formFieldListener: $listener);

        return $field;
    }

    public function testListenersOfANotEmptyValidValue(): void
    {
        $listener = new RecordingFieldListener();
        $field = $this->createField(listener: $listener);

        $field->validate(input: FormInput::fromArray(data: ['field' => 'x']));

        $this->assertSame(['notEmptyBefore', 'notEmptyAfter', 'success'], $listener->calls);
    }

    public function testListenersOfAnEmptyValueWithRequiredError(): void
    {
        $listener = new RecordingFieldListener();
        $field = $this->createField(listener: $listener, required: true);

        $field->validate(input: FormInput::fromArray(data: ['field' => '']));

        $this->assertSame(['emptyBefore', 'emptyAfter', 'error'], $listener->calls);
    }

    public function testValidateCurrentValueRunsTheListenersToo(): void
    {
        $listener = new RecordingFieldListener();
        $field = $this->createField(listener: $listener);

        $field->validateCurrentValue();

        $this->assertSame(['emptyBefore', 'emptyAfter', 'success'], $listener->calls);
    }

    public function testListenersSeeTheValueThatWasReadFromTheInput(): void
    {
        $listener = new RecordingFieldListener();
        $field = $this->createField(listener: $listener);

        $field->validate(input: FormInput::fromArray(data: ['field' => '   ']));

        $this->assertSame(['emptyBefore', 'emptyAfter', 'success'], $listener->calls);
    }

    public function testListenersRunOnceForRejectedInputWithItsError(): void
    {
        $listener = new RecordingFieldListener();
        $field = $this->createField(listener: $listener);

        $field->validate(input: FormInput::fromArray(data: ['field' => ['x']]));

        $this->assertSame(['emptyBefore', 'emptyAfter', 'error'], $listener->calls);
    }
}