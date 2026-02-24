<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use ArrayObject;
use actra\yuf\form\component\FormField;
use actra\yuf\form\FormRule;
use actra\yuf\html\HtmlText;
use UnexpectedValueException;

class ValidValueRule extends FormRule
{
    protected array $validValues;

    public function __construct(array $validValues, HtmlText $errorMessage)
    {
        parent::__construct($errorMessage);

        $this->validValues = $validValues;
    }

    public function validate(FormField $formField): bool
    {
        if ($formField->isValueEmpty()) {
            return true;
        }

        $fieldValue = $formField->getRawValue();

        if (is_scalar($fieldValue)) {
            return in_array($fieldValue, $this->validValues);
        }

        if (is_array($fieldValue) || $fieldValue instanceof ArrayObject) {
            return (count(array_diff($fieldValue, $this->validValues)) === 0);
        }

        throw new UnexpectedValueException('Could not handle field value for rule ' . __CLASS__);
    }
}