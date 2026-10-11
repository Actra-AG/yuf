<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\common;

use actra\yuf\common\AcceptingMailDomainResolver;
use actra\yuf\common\EmailAddressError;
use actra\yuf\common\EmailAddressErrorEnum;
use actra\yuf\common\SystemMailDomainResolver;
use actra\yuf\tests\Double\common\StubMailServerLookup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SystemMailDomainResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function nullMxProvider(): iterable
    {
        yield 'null MX as returned by getmxrr()' => [['']];
        yield 'null MX as root name' => [['.']];
        yield 'null MX next to a real MX' => [['mail.example.org', '']];
    }

    /**
     * @param list<string> $mxHosts
     */
    #[DataProvider('nullMxProvider')]
    public function testNullMxIsRejectedWithoutFallback(array $mxHosts): void
    {
        $lookup = new StubMailServerLookup(mxHosts: $mxHosts, ipv4Addresses: ['93.184.215.14']);

        $problem = new SystemMailDomainResolver(mailServerLookup: $lookup)->findProblem(domain: 'example.com');

        $this->assertSame(EmailAddressErrorEnum::NULL_MX, $problem?->code);
        $this->assertSame([], $lookup->connectedAddresses);
    }

    public function testDomainWithMxIsAccepted(): void
    {
        $lookup = new StubMailServerLookup(mxHosts: ['mail.example.org']);

        $this->assertNull(new SystemMailDomainResolver(mailServerLookup: $lookup)->findProblem(domain: 'example.org'));
        $this->assertSame([], $lookup->connectedAddresses);
    }

    public function testDomainWithoutMxIsAcceptedWithAnARecordThatAcceptsMail(): void
    {
        $lookup = new StubMailServerLookup(ipv4Addresses: ['93.184.215.14']);

        $this->assertNull(new SystemMailDomainResolver(mailServerLookup: $lookup)->findProblem(domain: 'example.org'));
        $this->assertSame(['93.184.215.14'], $lookup->connectedAddresses);
    }

    public function testDomainWithoutMxIsRejectedIfPort25IsClosed(): void
    {
        $lookup = new StubMailServerLookup(ipv4Addresses: ['93.184.215.14'], smtpConnectionProblem: 'refused');

        $problem = new SystemMailDomainResolver(mailServerLookup: $lookup)->findProblem(domain: 'example.org');

        $this->assertSame(EmailAddressErrorEnum::NOT_RESOLVABLE, $problem?->code);
        $this->assertSame('refused', $problem->message);
    }

    public function testPrivateAddressIsNotContacted(): void
    {
        $lookup = new StubMailServerLookup(ipv4Addresses: ['192.168.1.10']);

        $problem = new SystemMailDomainResolver(mailServerLookup: $lookup)->findProblem(domain: 'example.org');

        $this->assertSame(EmailAddressErrorEnum::NOT_RESOLVABLE, $problem?->code);
        $this->assertSame([], $lookup->connectedAddresses);
    }

    public function testDomainWithoutRecordsIsRejected(): void
    {
        $lookup = new StubMailServerLookup();

        $problem = new SystemMailDomainResolver(mailServerLookup: $lookup)->findProblem(domain: 'example.org');

        $this->assertSame(EmailAddressErrorEnum::NO_DNS_RECORDS, $problem?->code);
    }

    public function testFailedLookupIsReturned(): void
    {
        $error = new EmailAddressError(code: EmailAddressErrorEnum::DNS_GET_RECORD, message: 'timeout');
        $lookup = new StubMailServerLookup(ipv4Addresses: $error);

        $this->assertSame($error, new SystemMailDomainResolver(mailServerLookup: $lookup)->findProblem(domain: 'a.org'));
    }

    public function testAcceptingResolverAcceptsEveryDomain(): void
    {
        $this->assertNull(new AcceptingMailDomainResolver()->findProblem(domain: 'example.invalid'));
    }
}
