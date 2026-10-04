<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\component\layout\RadioOptionsLayout;
use actra\yuf\form\FormOptions;
use actra\yuf\form\FormRenderer;
use actra\yuf\form\renderer\DefinitionListRenderer;
use actra\yuf\form\renderer\LegendAndListRenderer;
use actra\yuf\form\renderer\RadioOptionsRenderer;
use actra\yuf\html\HtmlText;

class RadioOptionsField extends SingleOptionsField
{
    private bool $hasDefaultRequiredMessage = false;

    public function __construct(
        string $name,
        HtmlText $label,
        FormOptions $formOptions,
        ?string $initialValue,
        ?HtmlText $requiredError = null,
        RadioOptionsLayout $layout = RadioOptionsLayout::LEGEND_AND_LIST
    ) {
        parent::__construct(
            name: $name,
            label: $label,
            formOptions: $formOptions,
            initialValue: $initialValue,
            autoComplete: null
        );
        // Mandatory rule: In a field with radio options it is always required to choose one of those options
        if ($requiredError === null) {
            $this->hasDefaultRequiredMessage = true;
            $this->addRequiredRule(
                errorMessage: HtmlText::unencoded(textContent: $this->messages->selectOneOption)
            );
        } else {
            $this->addRequiredRule(errorMessage: $requiredError);
        }
        switch ($layout) {
            case RadioOptionsLayout::DEFINITION_LIST:
                $this->setRenderer(renderer: new DefinitionListRenderer(formField: $this));
                break;

            case RadioOptionsLayout::LEGEND_AND_LIST:
                $this->setRenderer(renderer: new LegendAndListRenderer(optionsField: $this));
                break;
            case RadioOptionsLayout::NONE:
                break;
        }
    }

    /**
     * The default text of the required rule comes from the messages of the form, which the field only gets after
     * its construction.
     */
    public function validateCurrentValue(): bool
    {
        if ($this->hasDefaultRequiredMessage) {
            $this->addRequiredRule(
                errorMessage: HtmlText::unencoded(textContent: $this->messages->selectOneOption)
            );
        }

        return parent::validateCurrentValue();
    }

    public function getDefaultRenderer(): FormRenderer
    {
        return new RadioOptionsRenderer(radioOptionsField: $this);
    }
}