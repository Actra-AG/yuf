<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\component\layout\CheckboxOptionsLayout;
use actra\yuf\form\FormOptions;
use actra\yuf\form\FormRenderer;
use actra\yuf\form\renderer\CheckboxItemRenderer;
use actra\yuf\form\renderer\CheckboxOptionsRenderer;
use actra\yuf\form\renderer\DefinitionListRenderer;
use actra\yuf\form\renderer\LegendAndListRenderer;
use actra\yuf\form\rule\RequiredRule;
use actra\yuf\html\HtmlText;
use UnexpectedValueException;

class CheckboxOptionsField extends OptionsField
{
    public function __construct(
        string $name,
        HtmlText $label,
        FormOptions $formOptions,
        array $initialValues,
        ?HtmlText $requiredError = null,
        CheckboxOptionsLayout $layout = CheckboxOptionsLayout::LEGEND_AND_LIST
    ) {
        parent::__construct(
            name: $name,
            label: $label,
            formOptions: $formOptions,
            initialValue: $initialValues,
            autoComplete: null
        );
        $this->acceptArrayAsValue();
        if (!is_null(value: $requiredError)) {
            $this->addRule(formRule: new RequiredRule(defaultErrorMessage: $requiredError));
        }
        switch ($layout) {
            case CheckboxOptionsLayout::DEFINITION_LIST:
                $this->setRenderer(renderer: new DefinitionListRenderer(formField: $this));
                break;
            case CheckboxOptionsLayout::LEGEND_AND_LIST:
                $this->setRenderer(renderer: new LegendAndListRenderer(optionsField: $this));
                break;
            case CheckboxOptionsLayout::CHECKBOX_ITEM:
                $this->setRenderer(renderer: new CheckboxItemRenderer(checkboxOptionsField: $this));
                break;
            case CheckboxOptionsLayout::NONE:
                break;
        }
    }

    /**
     * Returns the selected values as list, see FormField::getValuesAsStringListOrFail() for the exact rules. Also
     * works for a field that holds a single string (empty value gives `[]`, otherwise a list with this value).
     * The values are not checked against the options.
     *
     * @return list<string>
     * @throws UnexpectedValueException If the stored value contains an entry that is not a string (e.g. a nested
     *         array from manipulated input). Never thrown after a successful validation.
     */
    public function getValues(): array
    {
        return $this->getValuesAsStringListOrFail();
    }

    public function getDefaultRenderer(): FormRenderer
    {
        return new CheckboxOptionsRenderer(checkboxOptionsField: $this);
    }
}