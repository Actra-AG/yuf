<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form;

use actra\yuf\html\HtmlText;

/**
 * Base of all rules: it only stores the error message. A rule extends the typed base that fits the value of the
 * field it is added to: `StringRule`, `StringListRule`, `IntegerRule`, `FloatRule` or `DecimalRule`.
 */
abstract class FormRule
{
    private HtmlText $validationErrorMessage;

    public function __construct(HtmlText $defaultErrorMessage)
    {
        $this->validationErrorMessage = $defaultErrorMessage;
    }

    /**
     * Overwrite the error message for this rule.
     *
     * @param HtmlText $errorMessage : The new error message for this rule
     */
    public function setErrorMessage(HtmlText $errorMessage): void
    {
        $this->validationErrorMessage = $errorMessage;
    }

    public function getErrorMessage(): HtmlText
    {
        return $this->validationErrorMessage;
    }
}
