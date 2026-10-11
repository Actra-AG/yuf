<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\common\MailDomainResolver;
use actra\yuf\common\ValidatedEmailAddress;
use actra\yuf\html\HtmlText;
use Override;

/**
 * The text is an e-mail address: the syntax, and the domain with the resolver (`$formContext->mailDomainResolver`;
 * `null` checks the syntax only). The canonical form is set by the field (`EmailField`), not by the rule.
 */
final class ValidEmailAddressRule extends StringRule
{
    /**
     * @param bool $trueOnDnsError `true` accepts the address if the DNS lookup itself fails (no answer of the DNS
     *                             server), so a faulty network does not reject valid addresses
     */
    public function __construct(
        HtmlText $errorMessage,
        private readonly ?MailDomainResolver $mailDomainResolver,
        private readonly bool $trueOnDnsError = true,
    ) {
        parent::__construct(defaultErrorMessage: $errorMessage);
    }

    #[Override]
    public function validate(string $value): bool
    {
        if ($this->mailDomainResolver === null) {
            return new ValidatedEmailAddress(emailAddress: $value)->isValidSyntax;
        }
        $validatedEmailAddress = new ValidatedEmailAddress(
            emailAddress: $value,
            mailDomainResolver: $this->mailDomainResolver,
        );
        if (!$validatedEmailAddress->isValidSyntax) {
            return false;
        }

        return $validatedEmailAddress->isResolvable(returnTrueOnDnsGetRecordFailure: $this->trueOnDnsError);
    }
}
