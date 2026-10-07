<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\common\ValidatedEmailAddress;
use actra\yuf\html\HtmlText;

/**
 * The text is an e-mail address (syntax and, optionally, a DNS lookup of the domain). The canonical form is set by
 * the field (`EmailField`), not by the rule.
 */
class ValidEmailAddressRule extends StringRule
{
    public function __construct(
        HtmlText $errorMessage,
        private readonly bool $dnsCheck = true,
        private readonly bool $trueOnDnsError = true,
    ) {
        parent::__construct(defaultErrorMessage: $errorMessage);
    }

    public function validate(string $value): bool
    {
        $validatedEmailAddress = new ValidatedEmailAddress(emailAddress: $value);
        if (!$validatedEmailAddress->isValidSyntax) {
            return false;
        }
        if (!$this->dnsCheck) {
            return true;
        }

        return $validatedEmailAddress->isResolvable(returnTrueOnDnsGetRecordFailure: $this->trueOnDnsError);
    }
}
