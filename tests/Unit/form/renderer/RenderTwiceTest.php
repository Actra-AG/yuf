<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\renderer;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\BooleanField;
use actra\yuf\form\component\field\CheckboxOptionsField;
use actra\yuf\form\component\field\HiddenField;
use actra\yuf\form\component\field\MultiSelectOptionsField;
use actra\yuf\form\component\field\MultiToggleField;
use actra\yuf\form\component\field\NumericField;
use actra\yuf\form\component\field\RadioOptionsField;
use actra\yuf\form\component\field\SelectOptionsField;
use actra\yuf\form\component\field\TextAreaField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\field\ToggleField;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\component\FormInfo;
use actra\yuf\form\component\FormSubHeadline;
use actra\yuf\form\FormOptions;
use actra\yuf\form\renderer\DefinitionListRenderer;
use actra\yuf\form\renderer\InputFieldRenderer;
use actra\yuf\form\renderer\LegendAndListRenderer;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\form\FormContextFactory;
use PHPUnit\Framework\TestCase;

/**
 * A renderer keeps no tag: a component, a field and a whole form can be rendered more than once, with the same HTML.
 */
final class RenderTwiceTest extends TestCase
{
    private function text(string $text): HtmlText
    {
        return HtmlText::fromHtml(html: $text);
    }

    private function options(): FormOptions
    {
        $options = new FormOptions();
        $options->addItem(key: 'a', htmlText: $this->text('A'));
        $options->addItem(key: 'b', htmlText: $this->text('B'));

        return $options;
    }

    private function createForm(): Form
    {
        $form = new Form(context: FormContextFactory::create(), name: 'twice');
        $name = new TextField(name: 'customer', label: $this->text('Name'), requiredError: $this->text('Required'));
        $name->addError(errorMessage: $this->text('Wrong name'));
        $name->fieldInfo = $this->text('Info');
        $toggle = new ToggleField(
            name: 'delivery',
            label: $this->text('Delivery'),
            formOptions: $this->options(),
            initialValue: 'b',
        );
        $toggle->addChildField(
            mainOption: 'b',
            childField: new TextField(name: 'street', label: $this->text('Street')),
        );
        $multiToggle = new MultiToggleField(
            name: 'extras',
            label: $this->text('Extras'),
            formOptions: $this->options(),
            initialValues: ['a'],
            displayLegend: false,
        );
        $multiToggle->addChildField(
            mainOption: 'a',
            childField: new TextAreaField(name: 'remark', label: $this->text('Remark')),
        );
        $fields = [
            $name,
            new NumericField(name: 'zip', label: $this->text('Zip'), minLength: 4, maxLength: 5),
            new HiddenField(name: 'secret', value: 'x'),
            new SelectOptionsField(
                name: 'country',
                label: $this->text('Country'),
                formOptions: $this->options(),
                initialValue: 'a',
            ),
            new MultiSelectOptionsField(
                name: 'languages',
                label: $this->text('Languages'),
                formOptions: $this->options(),
                initialValues: ['b'],
            ),
            new CheckboxOptionsField(
                name: 'interests',
                label: $this->text('Interests'),
                formOptions: $this->options(),
                initialValues: ['a'],
            ),
            new RadioOptionsField(
                name: 'size',
                label: $this->text('Size'),
                formOptions: $this->options(),
                initialValue: 'b',
            ),
            new BooleanField(name: 'terms', label: $this->text('Terms'), isCheckedByDefault: true),
            $toggle,
            $multiToggle,
        ];
        foreach ($fields as $field) {
            $form->addField(formField: $field);
        }
        $form->addComponent(
            formComponent: new FormSubHeadline(headingLevel: 2, content: $this->text('Headline')),
        );
        $form->addComponent(formComponent: new FormInfo(title: $this->text('Title'), content: $this->text('Content')));
        $form->addComponent(formComponent: new FormControl(
            name: 'submit',
            submitLabel: $this->text('Send'),
            cancelLink: '/back',
        ));

        return $form;
    }

    public function testFormRenderedTwiceGivesTheSameHtml(): void
    {
        $form = $this->createForm();

        $first = $form->render();
        $second = $form->render();

        $this->assertSame($first, $second);
        $this->assertStringContainsString('<form ', $first);
        $this->assertStringContainsString('Wrong name', $first);
        $this->assertSame(1, substr_count($first, 'Wrong name'));
        $this->assertStringContainsString('name="remark"', $first);
    }

    public function testGetHtmlTagReturnsANewTagEveryTime(): void
    {
        $form = $this->createForm();

        $firstTag = $form->getHtmlTag();
        $secondTag = $form->getHtmlTag();

        $this->assertNotSame($firstTag, $secondTag);
        $this->assertSame($firstTag->render(), $secondTag->render());
    }

    public function testAFieldRenderedTwiceGivesTheSameHtml(): void
    {
        $field = new TextField(name: 'customer', label: $this->text('Name'), placeholder: 'Your name');

        $this->assertSame($field->render(), $field->render());
        $this->assertStringContainsString('placeholder="Your name"', $field->render());
    }

    public function testRenderersCreateANewTagEveryTime(): void
    {
        $field = new TextField(name: 'customer', label: $this->text('Name'));
        $renderers = [
            new InputFieldRenderer(formField: $field),
            new DefinitionListRenderer(formField: $field),
        ];
        $options = new RadioOptionsField(
            name: 'size',
            label: $this->text('Size'),
            formOptions: $this->options(),
            initialValue: 'a',
        );
        $renderers[] = new LegendAndListRenderer(optionsField: $options);

        foreach ($renderers as $renderer) {
            $first = $renderer->createHtmlTag();
            $second = $renderer->createHtmlTag();
            $this->assertNotSame($first, $second);
            $this->assertSame($first->render(), $second->render());
        }
    }

    public function testNumericFieldKeepsItsAttributesWhenRenderedTwice(): void
    {
        $field = new NumericField(name: 'zip', label: $this->text('Zip'), minLength: 4, maxLength: 4);

        $first = $field->render();

        $this->assertSame($first, $field->render());
        $this->assertSame(1, substr_count($first, 'inputmode="numeric"'));
        $this->assertStringContainsString('pattern="\d{4}"', $first);
    }
}
