<?php
/**
 * @author    Christof Moser <contact@actra.ch>
 * @copyright Actra AG, Embrach, Switzerland, www.actra.ch
 * @license   MIT
 */

namespace actra\yuf\form\component\field;

use actra\yuf\form\component\layout\RadioOptionsLayout;
use actra\yuf\form\FormOptions;
use actra\yuf\form\FormRenderer;
use actra\yuf\form\renderer\DefinitionListRenderer;
use actra\yuf\form\renderer\LegendAndListRenderer;
use actra\yuf\form\renderer\RadioOptionsRenderer;
use actra\yuf\form\rule\RequiredRule;
use actra\yuf\html\HtmlText;

class RadioOptionsField extends OptionsField
{
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
        if (is_null(value: $requiredError)) {
            // Mandatory rule: In a field with radio options it is always required to choose one of those options
            $requiredError = HtmlText::encoded(textContent: 'Bitte wählen Sie eine der Optionen aus.');
        }
        $this->addRule(formRule: new RequiredRule(defaultErrorMessage: $requiredError));
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

    public function getDefaultRenderer(): FormRenderer
    {
        return new RadioOptionsRenderer(radioOptionsField: $this);
    }
}