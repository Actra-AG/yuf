<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\form\component\FormField;
use actra\yuf\form\FormOptions;
use actra\yuf\form\FormRule;
use actra\yuf\html\HtmlText;
use UnexpectedValueException;

class ValidateAgainstOptions extends FormRule
{
    private FormOptions $validFormOptions;

    public function __construct(
        HtmlText    $errorMessage,
        FormOptions $validFormOptions
    )
    {
        $this->validFormOptions = $validFormOptions;

        parent::__construct(defaultErrorMessage: $errorMessage);
    }

    public function validate(FormField $formField): bool
    {
        if ($formField->isValueEmpty()) {
            return true;
        }
        $fieldValue = $formField->getRawValue();
        if (is_scalar(value: $fieldValue)) {
            return $this->validFormOptions->exists(key: $fieldValue);
        }
        if (is_array(value: $fieldValue)) {
            foreach ($fieldValue as $elementValue) {
                if (!is_scalar(value: $elementValue)) {
                    return false;
                }

                if (!$this->validFormOptions->exists(key: $elementValue)) {
                    return false;
                }
            }

            return true;
        }

        throw new UnexpectedValueException(message: 'The field value is neither a scalar data type nor an array');
    }
}