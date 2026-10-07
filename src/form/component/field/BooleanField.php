<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\component\FormField;
use actra\yuf\form\component\layout\CheckboxOptionsLayoutEnum;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormRenderer;
use actra\yuf\form\InputShapeEnum;
use actra\yuf\form\renderer\BooleanFieldListRenderer;
use actra\yuf\form\renderer\CheckboxItemRenderer;
use actra\yuf\form\renderer\DefinitionListRenderer;
use actra\yuf\html\HtmlText;
use LogicException;
use Override;

/**
 * A single checkbox. The value is a `bool`, empty is `false` (so a required rule means "must be checked").
 * The checkbox is posted as `name[]=checked` (a plain `name=checked` is accepted as well).
 */
class BooleanField extends FormField
{
    /** The posted value of the checked checkbox. */
    public const string CHECKED_KEY = 'checked';

    private bool $checked = false;
    private bool $initiallyChecked = false;

    public function __construct(
        string $name,
        HtmlText $label,
        bool $isCheckedByDefault,
        ?HtmlText $requiredError = null,
        CheckboxOptionsLayoutEnum $layout = CheckboxOptionsLayoutEnum::CHECKBOX_ITEM,
    ) {
        parent::__construct(
            name: $name,
            label: $label,
        );
        $this->setInitiallyChecked(checked: $isCheckedByDefault);
        if ($requiredError !== null) {
            $this->addRequiredRule(errorMessage: $requiredError);
        }
        match ($layout) {
            CheckboxOptionsLayoutEnum::DEFINITION_LIST => $this->setRenderer(
                renderer: new DefinitionListRenderer(formField: $this),
            ),
            CheckboxOptionsLayoutEnum::LEGEND_AND_LIST => $this->setRenderer(
                renderer: new BooleanFieldListRenderer(booleanField: $this, withLegend: true),
            ),
            CheckboxOptionsLayoutEnum::CHECKBOX_ITEM => $this->setRenderer(
                renderer: new CheckboxItemRenderer(checkboxOptionsField: $this),
            ),
            CheckboxOptionsLayoutEnum::NONE => null,
        };
    }

    public function isChecked(): bool
    {
        return $this->checked;
    }

    /**
     * Changes the current value only, the initial value stays (so `valueHasChanged()` compares with it).
     */
    public function setChecked(bool $checked): void
    {
        $this->checked = $checked;
    }

    /**
     * Sets the current and the initial value. For subclasses that fill the field after `parent::__construct()`.
     *
     * @throws LogicException If the field has already been validated.
     */
    protected function setInitiallyChecked(bool $checked): void
    {
        $this->assertInitialValueCanBeSet();
        $this->checked = $checked;
        $this->initiallyChecked = $checked;
    }

    #[Override]
    public function isValueEmpty(): bool
    {
        return !$this->checked;
    }

    #[Override]
    public function valueHasChanged(): bool
    {
        return $this->checked !== $this->initiallyChecked;
    }

    /**
     * The value of the checkbox as it is posted: `'checked'`, or `''` if it is not checked.
     */
    #[Override]
    public function renderValue(): string
    {
        return $this->checked ? BooleanField::CHECKED_KEY : '';
    }

    #[Override]
    public function getDefaultRenderer(): FormRenderer
    {
        // The markup of v3: a list with one checkbox (used by the layouts NONE and DEFINITION_LIST)
        return new BooleanFieldListRenderer(booleanField: $this, withLegend: false);
    }

    /**
     * Reads the value from the request: `checked` (as text or as the only entry of a list) is checked, a missing
     * value is not checked, anything else is rejected (not checked, one error, no rules).
     */
    #[Override]
    final protected function readInput(FormInput $input): void
    {
        $isChecked = match ($input->getShape(name: $this->name)) {
            InputShapeEnum::MISSING => false,
            InputShapeEnum::TEXT => $input->getText(name: $this->name) === BooleanField::CHECKED_KEY ? true : null,
            InputShapeEnum::LIST => $input->getList(name: $this->name) === [BooleanField::CHECKED_KEY] ? true : null,
            InputShapeEnum::INVALID => null,
        };
        $this->checked = $isChecked ?? false;
        if ($isChecked === null) {
            $this->rejectInput(errorMessage: $this->messages->invalidInput);
        }
    }
}
