<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\common;

use actra\yuf\common\EmailAddressError;
use actra\yuf\common\EmailAddressErrorEnum;
use actra\yuf\common\ValidatedEmailAddress;
use actra\yuf\tests\Double\common\FixedMailDomainResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidatedEmailAddressTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validProvider(): iterable
    {
        yield 'simple' => ['a@example.com', 'a@example.com'];
        yield 'lower case' => ['Anna.Muster@Example.COM', 'anna.muster@example.com'];
        yield 'surrounding whitespace' => ["  a@example.com\t\n", 'a@example.com'];
        yield 'whitespace inside' => ['a@exa mple.com', 'a@example.com'];
        yield 'zero width characters' => ["a\u{200B}@exam\u{200C}ple.com", 'a@example.com'];
        yield 'html entity of the zero width space' => ['a&#8203;@example.com', 'a@example.com'];
        yield 'international domain name' => ['x@Bücher.Example', 'x@xn--bcher-kva.example'];
        yield 'plus tag' => ['a+tag@example.com', 'a+tag@example.com'];
        yield 'apostrophe' => ["o'neil@example.com", "o'neil@example.com"];
        yield 'subdomain' => ['a@mail.example.co.uk', 'a@mail.example.co.uk'];
    }

    #[DataProvider('validProvider')]
    public function testValidAddresses(string $input, string $expected): void
    {
        $address = new ValidatedEmailAddress(emailAddress: $input);

        $this->assertTrue($address->isValidSyntax);
        $this->assertSame($expected, $address->validatedValue);
        $this->assertNull($address->lastErrorCode);
        $this->assertSame('', $address->lastErrorMessage);
    }

    /**
     * @return iterable<string, array{string, EmailAddressErrorEnum}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'empty' => ['', EmailAddressErrorEnum::EMPTY_VALUE];
        yield 'only whitespace' => ['   ', EmailAddressErrorEnum::EMPTY_VALUE];
        yield 'no at character' => ['a', EmailAddressErrorEnum::AT_CHARACTER];
        yield 'two at characters' => ['a@b@c.com', EmailAddressErrorEnum::AT_CHARACTER];
        yield 'no local part' => ['@example.com', EmailAddressErrorEnum::INVALID_SYNTAX];
        yield 'no domain' => ['a@', EmailAddressErrorEnum::INVALID_DOMAIN_NAME];
        yield 'domain starts with a dash' => ['a@-bad.com', EmailAddressErrorEnum::INVALID_DOMAIN_NAME];
        yield 'domain ends with a dash' => ['a@bad-.com', EmailAddressErrorEnum::INVALID_DOMAIN_NAME];
        yield 'domain starts with a dot' => ['a@.example.com', EmailAddressErrorEnum::INVALID_DOMAIN_NAME];
        yield 'empty domain label' => ['a@exam..ple.com', EmailAddressErrorEnum::INVALID_DOMAIN_NAME];
        yield 'label longer than 63 characters' => [
            'a@' . str_repeat(string: 'b', times: 64) . '.com',
            EmailAddressErrorEnum::INVALID_DOMAIN_NAME,
        ];
        yield 'no top level domain' => ['a@localhost', EmailAddressErrorEnum::INVALID_SYNTAX];
        yield 'one character top level domain' => ['a@example.c', EmailAddressErrorEnum::INVALID_CHARACTERS];
        yield 'comma' => ['a,b@example.com', EmailAddressErrorEnum::INVALID_SYNTAX];
        yield 'semicolon' => ['a;b@example.com', EmailAddressErrorEnum::INVALID_SYNTAX];
        yield 'mailto prefix' => ['mailto:a@example.com', EmailAddressErrorEnum::INVALID_SYNTAX];
        yield 'underscore in the domain' => ['a@exam_ple.com', EmailAddressErrorEnum::INVALID_SYNTAX];
        yield 'leading dot' => ['.a@example.com', EmailAddressErrorEnum::INVALID_SYNTAX];
        yield 'trailing dot' => ['a.@example.com', EmailAddressErrorEnum::INVALID_SYNTAX];
        yield 'two dots' => ['a..b@example.com', EmailAddressErrorEnum::INVALID_SYNTAX];
        yield 'ip address as domain' => ['a@123.123.123.123', EmailAddressErrorEnum::INVALID_SYNTAX];
        yield 'ip address in brackets' => ['a@[1.2.3.4]', EmailAddressErrorEnum::INVALID_CHARACTERS];
        yield 'quoted local part' => ['"a b"@example.com', EmailAddressErrorEnum::INVALID_CHARACTERS];
        yield 'local part longer than 64 characters' => [
            str_repeat(string: 'a', times: 65) . '@example.com',
            EmailAddressErrorEnum::INVALID_SYNTAX,
        ];
        yield 'non ascii local part' => ['jörg@example.com', EmailAddressErrorEnum::INVALID_SYNTAX];
        yield 'domain with a trailing dot' => ['a@example.com.', EmailAddressErrorEnum::INVALID_SYNTAX];
    }

    #[DataProvider('invalidProvider')]
    public function testInvalidAddresses(string $input, EmailAddressErrorEnum $expectedCode): void
    {
        $address = new ValidatedEmailAddress(emailAddress: $input);

        $this->assertFalse($address->isValidSyntax);
        $this->assertSame('', $address->validatedValue);
        $this->assertSame($expectedCode, $address->lastErrorCode);
        $this->assertNotSame('', $address->lastErrorMessage);
    }

    /**
     * @return iterable<string, array{string, EmailAddressErrorEnum}>
     */
    public static function errorCodeProvider(): iterable
    {
        yield 'emptyValue' => ['emptyValue', EmailAddressErrorEnum::EMPTY_VALUE];
        yield 'atCharacterError' => ['atCharacterError', EmailAddressErrorEnum::AT_CHARACTER];
        yield 'invalidDomainName' => ['invalidDomainName', EmailAddressErrorEnum::INVALID_DOMAIN_NAME];
        yield 'invalidSyntax' => ['invalidSyntax', EmailAddressErrorEnum::INVALID_SYNTAX];
        yield 'invalidCharacters' => ['invalidCharacters', EmailAddressErrorEnum::INVALID_CHARACTERS];
        yield 'dns_get_record' => ['dns_get_record', EmailAddressErrorEnum::DNS_GET_RECORD];
        yield 'noDnsRecords' => ['noDnsRecords', EmailAddressErrorEnum::NO_DNS_RECORDS];
        yield 'fsockopen' => ['fsockopen', EmailAddressErrorEnum::FSOCKOPEN];
        yield 'notResolvable' => ['notResolvable', EmailAddressErrorEnum::NOT_RESOLVABLE];
    }

    #[DataProvider('errorCodeProvider')]
    public function testErrorCodesKeepTheValuesOfEarlierVersions(string $code, EmailAddressErrorEnum $expected): void
    {
        $this->assertSame($expected, EmailAddressErrorEnum::tryFrom(value: $code));
    }

    public function testAnInvalidAddressIsNotResolvableAndNotChecked(): void
    {
        $resolver = new FixedMailDomainResolver();
        $address = new ValidatedEmailAddress(emailAddress: 'a@-bad.com', mailDomainResolver: $resolver);

        $this->assertFalse($address->isResolvable(returnTrueOnDnsGetRecordFailure: true));
        $this->assertSame([], $resolver->checkedDomains);
    }

    public function testResolvableDomain(): void
    {
        $resolver = new FixedMailDomainResolver();
        $address = new ValidatedEmailAddress(emailAddress: 'a@Bücher.example', mailDomainResolver: $resolver);

        $this->assertTrue($address->isResolvable(returnTrueOnDnsGetRecordFailure: false));
        $this->assertSame(['xn--bcher-kva.example'], $resolver->checkedDomains);
        $this->assertNull($address->lastErrorCode);
    }

    public function testDomainIsCheckedOnce(): void
    {
        $resolver = new FixedMailDomainResolver();
        $address = new ValidatedEmailAddress(emailAddress: 'a@example.com', mailDomainResolver: $resolver);

        $address->isResolvable(returnTrueOnDnsGetRecordFailure: false);
        $address->isResolvable(returnTrueOnDnsGetRecordFailure: false);

        $this->assertSame(['example.com'], $resolver->checkedDomains);
    }

    public function testDomainWithoutRecordsIsNotResolvable(): void
    {
        $resolver = new FixedMailDomainResolver(
            problem: new EmailAddressError(code: EmailAddressErrorEnum::NO_DNS_RECORDS, message: 'No A-Records'),
        );
        $address = new ValidatedEmailAddress(emailAddress: 'a@example.com', mailDomainResolver: $resolver);

        $this->assertFalse($address->isResolvable(returnTrueOnDnsGetRecordFailure: true));
        $this->assertSame(EmailAddressErrorEnum::NO_DNS_RECORDS, $address->lastErrorCode);
        $this->assertSame('No A-Records', $address->lastErrorMessage);
    }

    public function testFailedLookupIsAcceptedOnRequest(): void
    {
        $resolver = new FixedMailDomainResolver(
            problem: new EmailAddressError(code: EmailAddressErrorEnum::DNS_GET_RECORD, message: 'timeout'),
        );

        $strict = new ValidatedEmailAddress(emailAddress: 'a@example.com', mailDomainResolver: $resolver);
        $lenient = new ValidatedEmailAddress(emailAddress: 'a@example.com', mailDomainResolver: $resolver);

        $this->assertFalse($strict->isResolvable(returnTrueOnDnsGetRecordFailure: false));
        $this->assertTrue($lenient->isResolvable(returnTrueOnDnsGetRecordFailure: true));
        $this->assertSame(EmailAddressErrorEnum::DNS_GET_RECORD, $lenient->lastErrorCode);
    }

    public function testOtherProblemsAreNotAcceptedOnRequest(): void
    {
        $resolver = new FixedMailDomainResolver(
            problem: new EmailAddressError(code: EmailAddressErrorEnum::NOT_RESOLVABLE, message: 'no connection'),
        );
        $address = new ValidatedEmailAddress(emailAddress: 'a@example.com', mailDomainResolver: $resolver);

        $this->assertFalse($address->isResolvable(returnTrueOnDnsGetRecordFailure: true));
    }

    public function testTheAnswerStaysTheSameForTheOtherFlag(): void
    {
        $resolver = new FixedMailDomainResolver(
            problem: new EmailAddressError(code: EmailAddressErrorEnum::DNS_GET_RECORD, message: 'timeout'),
        );
        $address = new ValidatedEmailAddress(emailAddress: 'a@example.com', mailDomainResolver: $resolver);

        $this->assertFalse($address->isResolvable(returnTrueOnDnsGetRecordFailure: false));
        $this->assertTrue($address->isResolvable(returnTrueOnDnsGetRecordFailure: true));
        $this->assertSame(['example.com'], $resolver->checkedDomains);
    }
}
