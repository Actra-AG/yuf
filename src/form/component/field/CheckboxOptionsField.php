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

/**
 * Checkboxes: a list of selected option keys.
 *
 * Extension point: a project can extend it to fill its options or to change its rules.
 */
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
        $renderer = match ($layout) {
            CheckboxOptionsLayoutEnum::DEFINITION_LIST => new DefinitionListRenderer(formField: $this),
            CheckboxOptionsLayoutEnum::LEGEND_AND_LIST => new LegendAndListRenderer(optionsField: $this),
            CheckboxOptionsLayoutEnum::CHECKBOX_ITEM => new CheckboxItemRenderer(checkboxOptionsField: $this),
            CheckboxOptionsLayoutEnum::NONE => null,
        };
        if ($renderer !== null) {
            $this->setRenderer(renderer: $renderer);
        }
    }

    #[Override]
    public function getDefaultRenderer(): FormRenderer
    {
        return new CheckboxOptionsRenderer(checkboxOptionsField: $this);
    }
}
