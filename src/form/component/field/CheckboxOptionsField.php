<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\component\layout\CheckboxOptionsLayoutEnum;
use actra\yuf\form\FormOptions;
use actra\yuf\form\FormRenderer;
use actra\yuf\form\renderer\CheckboxItemRenderer;
use actra\yuf\form\renderer\CheckboxOptionsRenderer;
use actra\yuf\form\renderer\DefinitionListRenderer;
use actra\yuf\form\renderer\LegendAndListRenderer;
use actra\yuf\html\HtmlText;
use Override;

class CheckboxOptionsField extends MultiOptionsField
{
    /**
     * @param list<string> $initialValues
     */
    public function __construct(
        string $name,
        HtmlText $label,
        FormOptions $formOptions,
        array $initialValues,
        ?HtmlText $requiredError = null,
        CheckboxOptionsLayoutEnum $layout = CheckboxOptionsLayoutEnum::LEGEND_AND_LIST,
    ) {
        parent::__construct(
            name: $name,
            label: $label,
            formOptions: $formOptions,
            initialValues: $initialValues,
            autoComplete: null,
        );
        if ($requiredError !== null) {
            $this->addRequiredRule(errorMessage: $requiredError);
        }
        switch ($layout) {
            case CheckboxOptionsLayoutEnum::DEFINITION_LIST:
                $this->setRenderer(renderer: new DefinitionListRenderer(formField: $this));
                break;
            case CheckboxOptionsLayoutEnum::LEGEND_AND_LIST:
                $this->setRenderer(renderer: new LegendAndListRenderer(optionsField: $this));
                break;
            case CheckboxOptionsLayoutEnum::CHECKBOX_ITEM:
                $this->setRenderer(renderer: new CheckboxItemRenderer(checkboxOptionsField: $this));
                break;
            case CheckboxOptionsLayoutEnum::NONE:
                break;
        }
    }

    #[Override]
    public function getDefaultRenderer(): FormRenderer
    {
        return new CheckboxOptionsRenderer(checkboxOptionsField: $this);
    }
}
