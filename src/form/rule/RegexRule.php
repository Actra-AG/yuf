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

class RegexRule extends FormRule
{
    public function __construct(
        protected string $pattern,
        HtmlText $errorMessage
    ) {
        parent::__construct($errorMessage);
    }

    public function validate(FormField $formField): bool
    {
        if ($formField->isValueEmpty()) {
            return true;
        }

        $fieldValue = $formField->getRawValue();

        if (is_scalar($fieldValue)) {
            return $this->checkAgainstPattern($fieldValue);
        } elseif (is_array($fieldValue) || $fieldValue instanceof ArrayObject) {
            return array_all($fieldValue, fn($value) => $this->checkAgainstPattern($value));
        } else {
            throw new UnexpectedValueException('The field value is neither scalar nor an array');
        }
    }

    protected function checkAgainstPattern($value): bool
    {
        return (preg_match($this->pattern, $value) === 1);
    }
}