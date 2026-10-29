<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailbox;

use actra\yuf\mailbox\ImapEncryptionEnum;
use actra\yuf\mailbox\ImapSettings;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImapSettingsTest extends TestCase
{
    public function testDefaultPortOfTheEncryption(): void
    {
        $implicitTls = new ImapSettings(hostName: 'imap.example.com', userName: 'u', password: 'p');
        $startTls = new ImapSettings(
            hostName: 'imap.example.com',
            userName: 'u',
            password: 'p',
            encryption: ImapEncryptionEnum::STARTTLS,
        );

        $this->assertSame(993, $implicitTls->port);
        $this->assertSame(143, $startTls->port);
        $this->assertSame(10, $implicitTls->timeoutSeconds);
    }

    public function testPasswordIsHiddenInDumps(): void
    {
        $settings = new ImapSettings(hostName: 'imap.example.com', userName: 'u', password: 'secret-pass', port: 1993);

        $this->assertSame(
            [
                'hostName' => 'imap.example.com',
                'userName' => 'u',
                'password' => '(hidden)',
                'encryption' => 'implicit_tls',
                'port' => 1993,
                'timeoutSeconds' => 10,
            ],
            $settings->__debugInfo(),
        );
    }

    #[DataProvider('invalidSettingsProvider')]
    public function testInvalidSettingsAreRefused(
        string $hostName,
        string $userName,
        string $password,
        ?int $port,
        int $timeoutSeconds,
    ): void {
        $this->expectException(InvalidArgumentException::class);

        new ImapSettings(
            hostName: $hostName,
            userName: $userName,
            password: $password,
            port: $port,
            timeoutSeconds: $timeoutSeconds,
        );
    }

    /**
     * @return iterable<string, array{string, string, string, int|null, int}>
     */
    public static function invalidSettingsProvider(): iterable
    {
        yield 'empty host' => ['', 'u', 'p', null, 10];
        yield 'host with path' => ['imap.example.com/x', 'u', 'p', null, 10];
        yield 'empty user' => ['imap.example.com', '', 'p', null, 10];
        yield 'empty password' => ['imap.example.com', 'u', '', null, 10];
        yield 'line break in password' => ['imap.example.com', 'u', "p\r\nA1 LOGOUT", null, 10];
        yield 'NUL in user' => ['imap.example.com', "u\0", 'p', null, 10];
        yield 'port 0' => ['imap.example.com', 'u', 'p', 0, 10];
        yield 'port too high' => ['imap.example.com', 'u', 'p', 65536, 10];
        yield 'timeout 0' => ['imap.example.com', 'u', 'p', null, 0];
    }
}
