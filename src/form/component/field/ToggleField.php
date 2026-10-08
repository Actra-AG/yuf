<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\FormOptions;
use actra\yuf\form\FormRenderer;
use actra\yuf\form\renderer\ToggleFieldRenderer;
use actra\yuf\form\settings\AutoCompleteEnum;
use actra\yuf\html\HtmlText;
use Override;

/**
 * Radio options that show child components under the selected option. See `MultiToggleField` for checkboxes.
 */
final class ToggleField extends SingleOptionsField
{
    use HasToggleChildren;

    public function __construct(
        string $name,
        HtmlText $label,
        FormOptions $formOptions,
        ?string $initialValue,
        ?HtmlText $requiredError = null,
        private readonly bool $displayLegend = true,
        ?AutoCompleteEnum $autoComplete = null,
    ) {
        parent::__construct(
            name: $name,
            label: $label,
            formOptions: $formOptions,
            initialValue: $initialValue,
            autoComplete: $autoComplete,
        );
        if ($requiredError !== null) {
            $this->addRequiredRule(errorMessage: $requiredError);
        }
        // The toggle markup has always been fixed (the renderer the form would set is not used)
        $this->setRenderer(renderer: $this->getDefaultRenderer());
    }

    #[Override]
    public function getDefaultRenderer(): FormRenderer
    {
        return new ToggleFieldRenderer(
            toggleField: $this,
            toggleChildren: $this->getToggleChildren(),
            displayLegend: $this->displayLegend,
        );
    }
}
