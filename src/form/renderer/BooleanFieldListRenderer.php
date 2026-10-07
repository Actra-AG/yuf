<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\field\BooleanField;
use actra\yuf\form\component\field\CheckboxOptionsField;
use actra\yuf\form\component\layout\CheckboxOptionsLayoutEnum;
use actra\yuf\form\FormOptions;
use actra\yuf\form\FormRenderer;
use actra\yuf\html\HtmlText;
use Override;

/**
 * The v3 markup of a `BooleanField` that is not rendered as a single checkbox item: a list with one checkbox
 * (`id="name_checked"`), optionally in a fieldset with a legend. The v3 field was a `CheckboxOptionsField` with the
 * option `checked`; the markup is created by the options renderers from a copy of the field in that form.
 */
class BooleanFieldListRenderer extends FormRenderer
{
    public function __construct(
        private readonly BooleanField $booleanField,
        private readonly bool $withLegend,
    ) {}

    #[Override]
    public function prepare(): void
    {
        $optionsField = $this->createOptionsField();
        $renderer = $this->withLegend
            ? new LegendAndListRenderer(optionsField: $optionsField)
            : new CheckboxOptionsRenderer(checkboxOptionsField: $optionsField);
        $this->setHtmlTag(htmlTag: $renderer->prepareHtmlTag());
    }

    private function createOptionsField(): CheckboxOptionsField
    {
        $field = $this->booleanField;
        $formOptions = new FormOptions();
        $formOptions->addItem(key: BooleanField::CHECKED_KEY, htmlText: $field->label);
        $optionsField = new CheckboxOptionsField(
            name: $field->name,
            label: $field->label,
            formOptions: $formOptions,
            initialValues: $field->isChecked() ? [BooleanField::CHECKED_KEY] : [],
            layout: CheckboxOptionsLayoutEnum::NONE,
        );
        $optionsField->id = $field->id;
        $optionsField->fieldInfo = $field->fieldInfo;
        $optionsField->labelInfoText = $field->labelInfoText;
        $optionsField->renderRequiredAbbr = $field->renderRequiredAbbr;
        if (!$field->renderLabel) {
            $optionsField->setRenderLabelFalse();
        }
        if ($field->isRequired()) {
            $optionsField->addRequiredRule(errorMessage: HtmlText::encoded(textContent: ''));
        }
        foreach ($field->errorCollection->listErrors() as $error) {
            $optionsField->addError(errorMessage: $error);
        }

        return $optionsField;
    }
}
