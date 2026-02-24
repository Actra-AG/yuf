<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form;

use actra\yuf\form\component\FormField;
use actra\yuf\html\HtmlText;

abstract class FormRule
{
    private HtmlText $validationErrorMessage;

    public function __construct(HtmlText $defaultErrorMessage)
    {
        $this->validationErrorMessage = $defaultErrorMessage;
    }

    /**
     * Method to validate a form field.
     *
     * @param FormField $formField The field instance to check against
     *
     * @return bool
     */
    abstract public function validate(FormField $formField): bool;

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