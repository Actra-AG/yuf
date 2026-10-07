<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\MultiToggleField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\field\ToggleField;
use actra\yuf\form\component\FormField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\form\FormNameRegistry;
use actra\yuf\form\FormOptions;
use actra\yuf\form\FormRenderer;
use actra\yuf\form\renderer\DefaultComponentRenderer;
use actra\yuf\form\renderer\InputFieldRenderer;
use actra\yuf\html\HtmlText;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * The child components of `ToggleField` and `MultiToggleField` (`ToggleChildren`): registry, validation of the
 * children of the selected options, form handover and renderers.
 */
final class ToggleChildrenTest extends TestCase
{
    private function createOptions(): FormOptions
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: 'a', htmlText: HtmlText::encoded(textContent: 'A'));
        $formOptions->addItem(key: 'b', htmlText: HtmlText::encoded(textContent: 'B'));

        return $formOptions;
    }

    #[Override]
    protected function setUp(): void
    {
        FormNameRegistry::reset();
    }

    private function createToggle(?string $initialValue = null): ToggleField
    {
        return new ToggleField(
            name: 'toggle',
            label: HtmlText::encoded(textContent: 'Toggle'),
            formOptions: $this->createOptions(),
            initialValue: $initialValue,
        );
    }

    /**
     * @param list<string> $initialValues
     */
    private function createMultiToggle(array $initialValues = []): MultiToggleField
    {
        return new MultiToggleField(
            name: 'toggle',
            label: HtmlText::encoded(textContent: 'Toggle'),
            formOptions: $this->createOptions(),
            initialValues: $initialValues,
        );
    }

    private function createChild(string $name, bool $required = true): TextField
    {
        $field = new TextField(name: $name, label: HtmlText::encoded(textContent: $name));
        if ($required) {
            $field->addRequiredRule(errorMessage: HtmlText::encoded(textContent: 'Required'));
        }

        return $field;
    }

    public function testChildFieldCanBeRead(): void
    {
        $toggle = $this->createToggle();
        $child = $this->createChild(name: 'childA');

        $toggle->addChildField(mainOption: 'a', childField: $child);

        $this->assertSame($child, $toggle->getChildField(mainOption: 'a', fieldName: 'childA'));
        $this->assertSame($child, $toggle->getChildComponent(mainOption: 'a', componentName: 'childA'));
        $this->assertSame(['a' => ['childA' => $child]], $toggle->childrenByMainOption);
    }

    public function testChildOfAnUnknownMainOptionThrows(): void
    {
        $toggle = $this->createToggle();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The mainOption x does not exist!');

        $toggle->addChildField(mainOption: 'x', childField: $this->createChild(name: 'child'));
    }

    public function testUnknownChildThrows(): void
    {
        $toggle = $this->createToggle();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The mainOption a has no child nope');

        $toggle->getChildField(mainOption: 'a', fieldName: 'nope');
    }

    public function testChildErrorMakesTheToggleFieldHaveErrors(): void
    {
        $toggle = $this->createToggle(initialValue: 'a');
        $toggle->addChildField(mainOption: 'a', childField: $this->createChild(name: 'childA'));

        $isValid = $toggle->validate(input: FormInput::fromArray(data: ['toggle' => 'a', 'childA' => '']));

        $this->assertFalse($isValid);
        $this->assertFalse($toggle->hasErrors(withChildElements: false));
        $this->assertTrue($toggle->hasErrors(withChildElements: true));
    }

    public function testOnlyTheChildrenOfTheSelectedOptionAreValidated(): void
    {
        $toggle = $this->createToggle();
        $childA = $this->createChild(name: 'childA');
        $childB = $this->createChild(name: 'childB');
        $toggle->addChildField(mainOption: 'a', childField: $childA);
        $toggle->addChildField(mainOption: 'b', childField: $childB);

        $isValid = $toggle->validate(input: FormInput::fromArray(data: ['toggle' => 'b', 'childA' => '', 'childB' => 'ok']));

        $this->assertTrue($isValid);
        $this->assertSame('ok', $childB->getValueAsString());
        $this->assertSame('', $childA->getValueAsString());
        $this->assertFalse($childA->hasErrors(withChildElements: false));
    }

    public function testChildrenOfAllSelectedOptionsOfAMultiToggleAreValidated(): void
    {
        $toggle = $this->createMultiToggle();
        $toggle->addChildField(mainOption: 'a', childField: $this->createChild(name: 'childA'));
        $toggle->addChildField(mainOption: 'b', childField: $this->createChild(name: 'childB'));

        $isValid = $toggle->validate(input: FormInput::fromArray(data: ['toggle' => ['a', 'b'], 'childA' => 'x']));

        $this->assertFalse($isValid);
        $this->assertTrue($toggle->getChildField(mainOption: 'b', fieldName: 'childB')->hasErrors(false));
    }

    public function testChildrenAreNotValidatedIfTheToggleFieldIsInvalid(): void
    {
        $toggle = $this->createToggle();
        $child = $this->createChild(name: 'childA');
        $toggle->addChildField(mainOption: 'a', childField: $child);

        $isValid = $toggle->validate(input: FormInput::fromArray(data: ['toggle' => 'x', 'childA' => '']));

        $this->assertFalse($isValid);
        $this->assertFalse($child->hasErrors(withChildElements: false));
    }

    public function testNoOptionSelectedValidatesNoChildren(): void
    {
        $toggle = $this->createToggle();
        $child = $this->createChild(name: 'childA');
        $toggle->addChildField(mainOption: 'a', childField: $child);

        $this->assertTrue($toggle->validate(input: FormInput::fromArray(data: [])));
    }

    public function testChildrenGetTheFormOfTheToggleFieldWhenItIsAddedLater(): void
    {
        $toggle = $this->createToggle();
        $child = $this->createChild(name: 'childA');
        $toggle->addChildField(mainOption: 'a', childField: $child);
        $form = new Form(name: 'toggleChildrenLaterForm', messages: FormMessages::german());

        $form->addField(formField: $toggle);
        $toggle->validate(input: FormInput::fromArray(data: ['toggle' => 'a', 'childA' => ['x']]));

        $this->assertSame($form, $child->topFormComponent);
        $this->assertSame(
            'Die ungültige Eingabe wurde ignoriert.',
            $child->errorCollection->getFirstError()->render(),
        );
    }

    public function testChildrenGetTheFormOfTheToggleFieldWhenItIsAlreadyInTheForm(): void
    {
        $form = new Form(name: 'toggleChildrenEarlyForm');
        $toggle = $this->createToggle();
        $form->addField(formField: $toggle);
        $child = $this->createChild(name: 'childA');

        $toggle->addChildField(mainOption: 'a', childField: $child);

        $this->assertSame($form, $child->topFormComponent);
        $this->assertSame($form->messages, $child->messages);
    }

    public function testChildErrorsReachTheForm(): void
    {
        $form = new Form(name: 'toggleChildrenErrorForm');
        $toggle = $this->createToggle(initialValue: 'a');
        $toggle->addChildField(mainOption: 'a', childField: $this->createChild(name: 'childA'));
        $form->addField(formField: $toggle);

        $toggle->validate(input: FormInput::fromArray(data: ['toggle' => 'a']));

        $this->assertTrue($form->hasErrors(withChildElements: true));
    }

    public function testDefaultChildRendererIsADefinitionList(): void
    {
        $toggle = $this->createToggle(initialValue: 'a');
        $toggle->addChildField(mainOption: 'a', childField: $this->createChild(name: 'childA'));

        $html = $toggle->render();

        $this->assertStringContainsString('<div class="form-toggle-content" id="toggle_a"><dl><dt>', $html);
        $this->assertStringContainsString('aria-describedby="toggle_a"', $html);
    }

    public function testChildRendererFactoryIsUsedForChildFieldsWithoutRenderer(): void
    {
        $toggle = $this->createToggle(initialValue: 'a');
        $toggle->addChildField(mainOption: 'a', childField: $this->createChild(name: 'childA', required: false));
        $toggle->setDefaultChildFieldRenderer(
            rendererFactory: static fn(FormField $childField): FormRenderer => new DefaultComponentRenderer(
                formComponent: $childField,
            ),
        );

        $html = $toggle->render();

        $this->assertStringNotContainsString('<dl>', $html);
        $this->assertStringContainsString('<childA></childA>', $html);
    }

    public function testChildWithItsOwnRendererKeepsIt(): void
    {
        $toggle = $this->createToggle(initialValue: 'a');
        $child = $this->createChild(name: 'childA', required: false);
        $child->setRenderer(renderer: new InputFieldRenderer(formField: $child));
        $toggle->addChildField(mainOption: 'a', childField: $child);

        $this->assertStringNotContainsString('<dl>', $toggle->render());
    }

    public function testToggleFieldUsesItsDefaultRendererSetInTheConstructor(): void
    {
        $toggle = $this->createToggle();

        $this->assertNotNull($toggle->getRenderer());
        $this->expectException(LogicException::class);

        $toggle->setRenderer(renderer: $toggle->getDefaultRenderer());
    }

    public function testValidateCurrentValueValidatesTheChildrenOfTheSelectedOption(): void
    {
        $toggle = $this->createToggle(initialValue: 'a');
        $selectedChild = $this->createChild(name: 'childA');
        $otherChild = $this->createChild(name: 'childB');
        $toggle->addChildField(mainOption: 'a', childField: $selectedChild);
        $toggle->addChildField(mainOption: 'b', childField: $otherChild);

        $this->assertFalse($toggle->validateCurrentValue());
        $this->assertTrue($selectedChild->hasErrors(withChildElements: false));
        $this->assertFalse($otherChild->hasErrors(withChildElements: false));
    }

    public function testValidateCurrentValueOfAMultiToggleValidatesTheChildrenOfAllSelectedOptions(): void
    {
        $toggle = $this->createMultiToggle(initialValues: ['a', 'b']);
        $childA = $this->createChild(name: 'childA');
        $childB = $this->createChild(name: 'childB');
        $toggle->addChildField(mainOption: 'a', childField: $childA);
        $toggle->addChildField(mainOption: 'b', childField: $childB);

        $this->assertFalse($toggle->validateCurrentValue());
        $this->assertTrue($childA->hasErrors(withChildElements: false));
        $this->assertTrue($childB->hasErrors(withChildElements: false));
    }

    public function testValidateWithInputValidatesTheChildrenOnlyOnceWithTheInput(): void
    {
        $toggle = $this->createToggle();
        $child = $this->createChild(name: 'childA');
        $toggle->addChildField(mainOption: 'a', childField: $child);

        $isValid = $toggle->validate(input: FormInput::fromArray(data: ['toggle' => 'a', 'childA' => 'value']));

        $this->assertTrue($isValid);
        $this->assertSame('value', $child->getValueAsString());
        $this->assertFalse($child->hasErrors(withChildElements: false));
    }
}
