<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

/**
 * An email address, normalized (no invisible characters, lower case, punycode domain) and checked for its syntax;
 * on request also for a domain that can receive mail.
 */
final class ValidatedEmailAddress
{
    private const string ADDITIONAL_SYNTAX_PATTERN = '/^[a-zA-Z0-9.!#$%&\'*+=?^_`{|}~]'
        . '[a-zA-Z0-9.!#$%&\'*+\-=?^_`{|}~]*@'
        . '(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9]|[a-zA-Z0-9][-a-zA-Z0-9]*[a-zA-Z0-9])\.)+'
        . '[a-zA-Z0-9][-a-zA-Z0-9]*[a-zA-Z0-9]$/';

    /** The normalized address, an empty string if the syntax is not valid */
    public readonly string $validatedValue;
    public readonly bool $isValidSyntax;
    /** Why the syntax is not valid, or why the domain is not resolvable (after `isResolvable()`) */
    public private(set) ?EmailAddressErrorEnum $lastErrorCode = null;
    public private(set) string $lastErrorMessage = '';
    private readonly string $domain;
    private ?bool $isResolvable = null;

    public function __construct(
        string $emailAddress,
        private readonly MailDomainResolver $mailDomainResolver = new SystemMailDomainResolver(),
    ) {
        $sanitizedValue = mb_strtolower(
            string: ValidatedEmailAddress::removeInvalidWhitespaces(emailAddress: $emailAddress),
        );
        $result = ValidatedEmailAddress::validateSyntax(input: $sanitizedValue);
        if ($result instanceof EmailAddressError) {
            $this->isValidSyntax = false;
            $this->validatedValue = '';
            $this->domain = '';
            $this->lastErrorCode = $result->code;
            $this->lastErrorMessage = $result->message;

            return;
        }
        $this->isValidSyntax = true;
        $this->validatedValue = $result['address'];
        $this->domain = $result['domain'];
    }

    /**
     * @param bool $returnTrueOnDnsGetRecordFailure `true` accepts the address if the DNS lookup itself fails (no
     *                                              answer of the DNS server), so a faulty network does not reject
     *                                              valid addresses
     */
    public function isResolvable(bool $returnTrueOnDnsGetRecordFailure): bool
    {
        if (!$this->isValidSyntax) {
            return false;
        }
        if ($this->isResolvable === null) {
            $problem = $this->mailDomainResolver->findProblem(domain: $this->domain);
            $this->isResolvable = $problem === null;
            if ($problem !== null) {
                $this->lastErrorCode = $problem->code;
                $this->lastErrorMessage = $problem->message;
            }
        }

        return $this->isResolvable
            || ($returnTrueOnDnsGetRecordFailure && $this->lastErrorCode === EmailAddressErrorEnum::DNS_GET_RECORD);
    }

    /**
     * An Email address never has spaces/tabs/newlines in it (they might get into that string by c&p error done by
     * users).
     */
    private static function removeInvalidWhitespaces(string $emailAddress): string
    {
        return trim(
            string: str_replace(
                search: [
                    ' ',
                    "\t",
                    "\n",
                    "\r",
                    '&#8203;',
                    "\xE2\x80\x8C",
                    "\xE2\x80\x8B", // https://stackoverflow.com/questions/22600235/remove-unicode-zero-width-space-php
                ],
                replace: '',
                subject: $emailAddress,
            ),
        );
    }

    /**
     * We purposely do NOT allow commas/semicolons (preventing "multiple" email address entered, where NOT expected)
     * ':' Will catch "mailto:" copy&paste errors from users, which also result in an invalid email address
     *
     * @return array{address: string, domain: string}|EmailAddressError The address with the domain in punycode
     */
    private static function validateSyntax(string $input): array|EmailAddressError
    {
        if ($input === '') {
            return new EmailAddressError(
                code: EmailAddressErrorEnum::EMPTY_VALUE,
                message: 'Empty email address value.',
            );
        }
        $emailParts = explode(separator: '@', string: $input);
        if (count(value: $emailParts) !== 2) {
            return new EmailAddressError(
                code: EmailAddressErrorEnum::AT_CHARACTER,
                message: 'The email address contains not exactly one at-character (@).',
            );
        }
        [$local, $unicodeDomain] = $emailParts;
        $domain = $unicodeDomain === '' ? false : idn_to_ascii(domain: $unicodeDomain);
        if ($domain === false) {
            return new EmailAddressError(
                code: EmailAddressErrorEnum::INVALID_DOMAIN_NAME,
                message: 'The email address contains an invalid domain part.',
            );
        }
        $address = $local . '@' . $domain;
        if (filter_var(value: $address, filter: FILTER_VALIDATE_EMAIL) === false) {
            return new EmailAddressError(
                code: EmailAddressErrorEnum::INVALID_SYNTAX,
                message: 'The FILTER_VALIDATE_EMAIL filter returned false due to an invalid syntax.',
            );
        }
        if (preg_match(pattern: ValidatedEmailAddress::ADDITIONAL_SYNTAX_PATTERN, subject: $address) !== 1) {
            return new EmailAddressError(
                code: EmailAddressErrorEnum::INVALID_CHARACTERS,
                message: 'The additional syntax validation failed.',
            );
        }

        return ['address' => $address, 'domain' => $domain];
    }
}
