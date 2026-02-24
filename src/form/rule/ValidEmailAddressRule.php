<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\common\ValidatedEmailAddress;
use actra\yuf\form\component\FormField;
use actra\yuf\form\FormRule;
use actra\yuf\html\HtmlText;

class ValidEmailAddressRule extends FormRule
{
    public function __construct(
        HtmlText $errorMessage,
        private readonly bool $dnsCheck = true,
        private readonly bool $trueOnDnsError = true
    ) {
        parent::__construct(defaultErrorMessage: $errorMessage);
    }

    public function validate(FormField $formField): bool
    {
        if ($formField->isValueEmpty()) {
            return true;
        }
        $fieldValue = (string)$formField->getRawValue();
        $validatedEmailAddress = new ValidatedEmailAddress(emailAddress: $fieldValue);
        if (!$validatedEmailAddress->isValidSyntax) {
            return false;
        }
        $formField->setValue(value: $validatedEmailAddress->validatedValue);
        if (!$this->dnsCheck) {
            return true;
        }

        return $validatedEmailAddress->isResolvable(returnTrueOnDnsGetRecordFailure: $this->trueOnDnsError);
    }
}