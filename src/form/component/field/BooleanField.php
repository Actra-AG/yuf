<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\component\layout\CheckboxOptionsLayout;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;

class BooleanField extends CheckboxOptionsField
{
    public function __construct(
        string $name,
        HtmlText $label,
        bool $isCheckedByDefault,
        ?HtmlText $requiredError = null,
        CheckboxOptionsLayout $layout = CheckboxOptionsLayout::CHECKBOX_ITEM
    ) {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: '1', htmlText: $label);
        parent::__construct(
            name: $name,
            label: $label,
            formOptions: $formOptions,
            initialValues: $isCheckedByDefault ? ['1'] : [],
            requiredError: $requiredError,
            layout: $layout
        );
    }

    public function validate(array $inputData, bool $overwriteValue = true): bool
    {
        if ($overwriteValue) {
            $this->setValue(
                value: array_key_exists(
                    key: $this->name,
                    array: $inputData
                ) ? $inputData[$this->name] : null
            );
        }

        return parent::validate(inputData: $inputData, overwriteValue: false);
    }

    public function getRawValue(bool $returnNullIfEmpty = false): int
    {
        return (int)parent::getRawValue(returnNullIfEmpty: $returnNullIfEmpty);
    }
}