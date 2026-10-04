<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\renderer;

use actra\yuf\form\component\field\BooleanField;
use actra\yuf\form\component\field\CheckboxOptionsField;
use actra\yuf\form\component\field\MultiSelectOptionsField;
use actra\yuf\form\component\field\MultiToggleField;
use actra\yuf\form\component\field\RadioOptionsField;
use actra\yuf\form\component\field\SelectOptionsField;
use actra\yuf\form\component\field\ToggleField;
use actra\yuf\form\component\layout\CheckboxOptionsLayout;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;

/**
 * The option renderers mark the selected options with `isSelected()`, they do not read the value themselves.
 */
final class OptionsFieldRenderersTest extends TestCase
{
    private function createOptions(): FormOptions
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: 'a', htmlText: HtmlText::encoded(textContent: 'A'));
        $formOptions->addItem(key: 'b', htmlText: HtmlText::encoded(textContent: 'B'));
        $formOptions->addItem(key: '0', htmlText: HtmlText::encoded(textContent: 'Zero'));

        return $formOptions;
    }

    private function label(): HtmlText
    {
        return HtmlText::encoded(textContent: 'Label');
    }

    public function testRadioMarksOnlyTheSelectedOption(): void
    {
        $field = new RadioOptionsField(
            name: 'r',
            label: $this->label(),
            formOptions: $this->createOptions(),
            initialValue: 'b'
        );

        $html = $field->render();

        $this->assertStringContainsString('<input type="radio" name="r" id="r_b" value="b" checked>', $html);
        $this->assertStringContainsString('<input type="radio" name="r" id="r_a" value="a">', $html);
        $this->assertSame(1, substr_count(haystack: $html, needle: 'checked'));
    }

    public function testRadioWithoutValueMarksNothing(): void
    {
        $field = new RadioOptionsField(
            name: 'r',
            label: $this->label(),
            formOptions: $this->createOptions(),
            initialValue: null
        );

        $this->assertStringNotContainsString('checked', $field->render());
    }

    public function testRadioMarksTheZeroKey(): void
    {
        $field = new RadioOptionsField(
            name: 'r',
            label: $this->label(),
            formOptions: $this->createOptions(),
            initialValue: '0'
        );

        $this->assertStringContainsString('id="r_0" value="0" checked>', $field->render());
    }

    public function testRadioMarksThePostedOption(): void
    {
        $field = new RadioOptionsField(
            name: 'r',
            label: $this->label(),
            formOptions: $this->createOptions(),
            initialValue: 'b'
        );
        $field->validate(inputData: ['r' => 'a']);

        $html = $field->render();

        $this->assertStringContainsString('id="r_a" value="a" checked>', $html);
        $this->assertSame(1, substr_count(haystack: $html, needle: 'checked'));
    }

    public function testCheckboxMarksAllSelectedOptions(): void
    {
        $field = new CheckboxOptionsField(
            name: 'c',
            label: $this->label(),
            formOptions: $this->createOptions(),
            initialValues: ['a', '0']
        );

        $html = $field->render();

        $this->assertStringContainsString('<input type="checkbox" name="c[]" id="c_a" value="a" checked>', $html);
        $this->assertStringContainsString('<input type="checkbox" name="c[]" id="c_b" value="b">', $html);
        $this->assertStringContainsString('<input type="checkbox" name="c[]" id="c_0" value="0" checked>', $html);
    }

    public function testCheckboxItemLayoutMarksTheFirstOptionWhenSelected(): void
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: 'yes', htmlText: HtmlText::encoded(textContent: 'Yes'));
        $field = new CheckboxOptionsField(
            name: 'c',
            label: $this->label(),
            formOptions: $formOptions,
            initialValues: ['yes'],
            layout: CheckboxOptionsLayout::CHECKBOX_ITEM
        );

        $this->assertStringContainsString(
            '<input type="checkbox" name="c[]" id="c" value="yes" checked>',
            $field->render()
        );
    }

    public function testCheckboxItemLayoutIsNotCheckedWithoutValue(): void
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: 'yes', htmlText: HtmlText::encoded(textContent: 'Yes'));
        $field = new CheckboxOptionsField(
            name: 'c',
            label: $this->label(),
            formOptions: $formOptions,
            initialValues: [],
            layout: CheckboxOptionsLayout::CHECKBOX_ITEM
        );

        $this->assertStringContainsString('<input type="checkbox" name="c[]" id="c" value="yes">', $field->render());
    }

    public function testBooleanFieldRendersACheckedCheckbox(): void
    {
        $field = new BooleanField(name: 'bo', label: $this->label(), isCheckedByDefault: true);

        $this->assertSame(
            '<div class="form-check"><input type="checkbox" name="bo[]" id="bo" value="checked" checked>'
            . '<label for="bo" class="form-check-label">Label</label></div>',
            $field->render()
        );
    }

    public function testBooleanFieldRendersAnUncheckedCheckbox(): void
    {
        $field = new BooleanField(name: 'bo', label: $this->label(), isCheckedByDefault: false);

        $this->assertStringContainsString(
            '<input type="checkbox" name="bo[]" id="bo" value="checked">',
            $field->render()
        );
    }

    public function testBooleanFieldRendersTheCurrentValueAfterInput(): void
    {
        $field = new BooleanField(name: 'bo', label: $this->label(), isCheckedByDefault: true);
        $field->validate(inputData: []);

        $this->assertStringNotContainsString('checked>', $field->render());
    }

    public function testBooleanFieldRendersTheError(): void
    {
        $field = new BooleanField(
            name: 'bo',
            label: $this->label(),
            isCheckedByDefault: false,
            requiredError: HtmlText::encoded(textContent: 'Accept')
        );
        $field->validate(inputData: []);

        $html = $field->render();

        $this->assertStringContainsString('class="form-check has-error"', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('Accept', $html);
    }

    public function testSelectMarksTheSelectedOption(): void
    {
        $field = new SelectOptionsField(
            name: 's',
            label: $this->label(),
            formOptions: $this->createOptions(),
            initialValue: 'b'
        );

        $this->assertSame(
            '<select name="s" id="s"><option value=""></option><option value="a">A</option>'
            . '<option value="b" selected>B</option><option value="0">Zero</option></select>',
            $field->render()
        );
    }

    public function testSelectWithoutValueSelectsTheEmptyOption(): void
    {
        $field = new SelectOptionsField(
            name: 's',
            label: $this->label(),
            formOptions: $this->createOptions(),
            initialValue: null
        );

        $this->assertStringContainsString('<option value="" selected></option>', $field->render());
    }

    public function testSelectMarksTheZeroOption(): void
    {
        $field = new SelectOptionsField(
            name: 's',
            label: $this->label(),
            formOptions: $this->createOptions(),
            initialValue: '0'
        );

        $this->assertStringContainsString('<option value="0" selected>Zero</option>', $field->render());
    }

    public function testMultiSelectIsMultipleAndMarksAllSelectedOptions(): void
    {
        $field = new MultiSelectOptionsField(
            name: 'm',
            label: $this->label(),
            formOptions: $this->createOptions(),
            initialValues: ['a', '0']
        );

        $this->assertSame(
            '<select name="m[]" id="m" multiple><option value=""></option><option value="a" selected>A</option>'
            . '<option value="b">B</option><option value="0" selected>Zero</option></select>',
            $field->render()
        );
    }

    public function testMultiSelectWithoutValuesSelectsNothing(): void
    {
        $field = new MultiSelectOptionsField(
            name: 'm',
            label: $this->label(),
            formOptions: $this->createOptions(),
            initialValues: []
        );

        $this->assertStringNotContainsString('selected', $field->render());
    }

    public function testMultiSelectRendersThePostedValues(): void
    {
        $field = new MultiSelectOptionsField(
            name: 'm',
            label: $this->label(),
            formOptions: $this->createOptions(),
            initialValues: ['a']
        );
        $field->validate(inputData: ['m' => ['b']]);

        $html = $field->render();

        $this->assertStringContainsString('<option value="b" selected>B</option>', $html);
        $this->assertStringContainsString('<option value="a">A</option>', $html);
    }

    public function testSelectRendersPlaceholderDataAttributesAndClasses(): void
    {
        $field = new SelectOptionsField(
            name: 's',
            label: $this->label(),
            formOptions: $this->createOptions(),
            initialValue: null,
            cssClasses: ['wide'],
            renderEmptyValueOption: false,
            placeholder: 'Pick'
        );
        $field->addDataAttribute(name: 'data-x', value: '1');

        $html = $field->render();

        $this->assertStringContainsString('data-x="1"', $html);
        $this->assertStringContainsString('class="wide"', $html);
        $this->assertStringContainsString('placeholder="Pick"', $html);
        $this->assertStringNotContainsString('<option value=""', $html);
    }

    public function testToggleRendersARadioListAndMarksTheSelectedOption(): void
    {
        $field = new ToggleField(
            name: 't',
            label: $this->label(),
            formOptions: $this->createOptions(),
            initialValue: 'b',
            displayLegend: false
        );

        $html = $field->render();

        $this->assertStringStartsWith('<div class="form-element"><ul class="form-toggle-list">', $html);
        $this->assertStringContainsString('<input type="radio" toggle-id="t_b" name="t" value="b" checked>', $html);
        $this->assertStringContainsString('<input type="radio" toggle-id="t_a" name="t" value="a">', $html);
    }

    public function testToggleWithLegendRendersAFieldset(): void
    {
        $field = new ToggleField(
            name: 't',
            label: $this->label(),
            formOptions: $this->createOptions(),
            initialValue: null,
            requiredError: HtmlText::encoded(textContent: 'Required')
        );

        $this->assertStringStartsWith(
            '<fieldset class="legend-and-list"><legend>Label<span class="required">*</span></legend>'
            . '<ul class="form-toggle-list">',
            $field->render()
        );
    }

    public function testMultiToggleRendersCheckboxesAndMarksAllSelectedOptions(): void
    {
        $field = new MultiToggleField(
            name: 'mt',
            label: $this->label(),
            formOptions: $this->createOptions(),
            initialValues: ['a', 'b'],
            displayLegend: false
        );

        $html = $field->render();

        $this->assertStringContainsString(
            '<input type="checkbox" toggle-id="mt_a" name="mt[]" value="a" checked>',
            $html
        );
        $this->assertStringContainsString(
            '<input type="checkbox" toggle-id="mt_b" name="mt[]" value="b" checked>',
            $html
        );
        $this->assertStringContainsString('<input type="checkbox" toggle-id="mt_0" name="mt[]" value="0">', $html);
    }

    public function testToggleRendersTheErrorOfTheField(): void
    {
        $field = new ToggleField(
            name: 't',
            label: $this->label(),
            formOptions: $this->createOptions(),
            initialValue: null,
            requiredError: HtmlText::encoded(textContent: 'Required'),
            displayLegend: false
        );
        $field->validate(inputData: []);

        $html = $field->render();

        $this->assertStringContainsString('<ul class="form-toggle-list list-has-error">', $html);
        $this->assertStringContainsString('class="form-element has-error"', $html);
        $this->assertStringContainsString('Required', $html);
    }
}