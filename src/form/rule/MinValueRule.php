<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\form\component\FormField;
use actra\yuf\form\FormRule;
use actra\yuf\html\HtmlText;
use UnexpectedValueException;

class MinValueRule extends FormRule
{
    protected int|float $minValue;

    public function __construct(int|float $minValue, HtmlText $errorMessage)
    {
        parent::__construct($errorMessage);

        $this->minValue = $minValue;
    }

    public function validate(FormField $formField): bool
    {
        if ($formField->isValueEmpty()) {
            return true;
        }

        $fieldValue = $formField->getRawValue();

        if (is_int($fieldValue) || is_float($fieldValue)) {
            return ($fieldValue >= $this->minValue);
        }

        throw new UnexpectedValueException('Could not handle field value for rule ' . __CLASS__);
    }
}