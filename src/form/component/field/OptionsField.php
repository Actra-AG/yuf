<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\component\FormField;
use actra\yuf\form\FormOptions;
use actra\yuf\form\rule\ValidateAgainstOptions;
use actra\yuf\form\settings\AutoCompleteValue;
use actra\yuf\html\HtmlText;

abstract class OptionsField extends FormField
{
    public ?HtmlText $listDescription = null;
    private array $listTagClasses = [];

    public function __construct(
        string $name,
        HtmlText $label,
        public FormOptions $formOptions,
        mixed $initialValue,
        public readonly ?AutoCompleteValue $autoComplete
    ) {
        parent::__construct(
            name: $name,
            label: $label,
            value: $initialValue
        );
        // We set a default error message because in normal circumstance this case cannot happen if the user chooses
        // available options, so it doesn't make sense to always set an individual error message for this check.
        // It can only happen by data manipulation, which we don't want to be notified about (by exception).
        $this->addRule(
            formRule: new ValidateAgainstOptions(
                errorMessage: HtmlText::encoded(textContent: 'Selected invalid value in field ' . $name),
                validFormOptions: $this->formOptions
            )
        );
    }

    public function addListTagClass(string $className): void
    {
        $this->listTagClasses[] = $className;
    }

    public function getListTagClasses(): array
    {
        return array_unique(array: $this->listTagClasses);
    }
}