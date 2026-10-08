<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\component\layout\RadioOptionsLayoutEnum;
use actra\yuf\form\FormOptions;
use actra\yuf\form\FormRenderer;
use actra\yuf\form\renderer\DefinitionListRenderer;
use actra\yuf\form\renderer\LegendAndListRenderer;
use actra\yuf\form\renderer\RadioOptionsRenderer;
use actra\yuf\html\HtmlText;
use Override;

/**
 * Radio buttons: one option must be chosen (the required rule is always set).
 *
 * Extension point: a project can extend it to fill its options or to change its rules.
 */
class RadioOptionsField extends SingleOptionsField
{
    private bool $hasDefaultRequiredMessage = false;

    public function __construct(
        string $name,
        HtmlText $label,
        FormOptions $formOptions,
        ?string $initialValue,
        ?HtmlText $requiredError = null,
        RadioOptionsLayoutEnum $layout = RadioOptionsLayoutEnum::LEGEND_AND_LIST,
    ) {
        parent::__construct(
            name: $name,
            label: $label,
            formOptions: $formOptions,
            initialValue: $initialValue,
            autoComplete: null,
        );
        // Mandatory rule: In a field with radio options it is always required to choose one of those options
        if ($requiredError === null) {
            $this->hasDefaultRequiredMessage = true;
            $this->addRequiredRule(
                errorMessage: HtmlText::fromText(text: $this->messages->selectOneOption),
            );
        } else {
            $this->addRequiredRule(errorMessage: $requiredError);
        }
        $renderer = match ($layout) {
            RadioOptionsLayoutEnum::DEFINITION_LIST => new DefinitionListRenderer(formField: $this),
            RadioOptionsLayoutEnum::LEGEND_AND_LIST => new LegendAndListRenderer(optionsField: $this),
            RadioOptionsLayoutEnum::NONE => null,
        };
        if ($renderer !== null) {
            $this->setRenderer(renderer: $renderer);
        }
    }

    /**
     * The default text of the required rule comes from the messages of the form, which the field only gets after
     * its construction.
     */
    #[Override]
    public function validateCurrentValue(): bool
    {
        if ($this->hasDefaultRequiredMessage) {
            $this->addRequiredRule(
                errorMessage: HtmlText::fromText(text: $this->messages->selectOneOption),
            );
        }

        return parent::validateCurrentValue();
    }

    #[Override]
    public function getDefaultRenderer(): FormRenderer
    {
        return new RadioOptionsRenderer(radioOptionsField: $this);
    }
}
