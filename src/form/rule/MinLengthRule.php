<?php
/**
 * @author    Christof Moser <contact@actra.ch>
 * @copyright Actra AG, Embrach, Switzerland, www.actra.ch
 * @license   MIT
 */

namespace actra\yuf\form\rule;

use ArrayObject;
use actra\yuf\form\component\FormField;
use actra\yuf\form\FormRule;
use actra\yuf\html\HtmlText;
use UnexpectedValueException;

class MinLengthRule extends FormRule
{
    protected int $minLength;

    public function __construct(int $minLength, HtmlText $errorMessage)
    {
        $this->minLength = $minLength;

        parent::__construct($errorMessage);
    }

    public function validate(FormField $formField): bool
    {
        if ($formField->isValueEmpty()) {
            return true;
        }

        $fieldValue = $formField->getRawValue();

        if (is_scalar($fieldValue)) {
            return $this->checkValueLengthAgainst(mb_strlen($fieldValue));
        }
        if (is_array($fieldValue) || $fieldValue instanceof ArrayObject) {
            return $this->checkValueLengthAgainst(count($fieldValue));
        }
        throw new UnexpectedValueException('Could not handle field value for rule ' . __CLASS__);
    }

    private function checkValueLengthAgainst($valueLength): bool
    {
        return ($valueLength >= $this->minLength);
    }
}