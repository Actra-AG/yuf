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

class MaxLengthRule extends FormRule
{
    protected int $maxLength;

    public function __construct(int $maxLength, HtmlText $errorMessage)
    {
        $this->maxLength = $maxLength;

        parent::__construct($errorMessage);
    }

    public function validate(FormField $formField): bool
    {
        if ($formField->isValueEmpty()) {
            return true;
        }

        $fieldValue = $formField->getRawValue();

        if (is_scalar($fieldValue)) {
            return $this->checkValueLengthAgainst(valueLength: mb_strlen(string: $fieldValue));
        }
        if (is_array($fieldValue) || $fieldValue instanceof ArrayObject) {
            return $this->checkValueLengthAgainst(count($fieldValue));
        }
        throw new UnexpectedValueException('Could not handle field value for rule ' . __CLASS__);
    }

    private function checkValueLengthAgainst($valueLength): bool
    {
        return ($valueLength <= $this->maxLength);
    }
}