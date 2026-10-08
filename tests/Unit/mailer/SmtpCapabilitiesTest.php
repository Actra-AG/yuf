<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\mailer\SmtpAuthMethodEnum;
use actra\yuf\mailer\SmtpCapabilities;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SmtpCapabilitiesTest extends TestCase
{
    /**
     * @return array<string, array{string, bool, list<string>}>
     */
    public static function replies(): array
    {
        return [
            'no AUTH line' => ["250-mx\r\n250-SIZE 100\r\n250 STARTTLS\r\n", false, []],
            'single line answer' => ["250 mx\r\n", false, []],
            'one line' => ["250-mx\r\n250 AUTH LOGIN PLAIN\r\n", true, ['LOGIN', 'PLAIN']],
            'in the middle' => ["250-mx\r\n250-AUTH PLAIN XOAUTH2\r\n250 8BITMIME\r\n", true, ['PLAIN', 'XOAUTH2']],
            'lower case' => ["250-mx\r\n250 auth login plain\r\n", true, ['LOGIN', 'PLAIN']],
            'legacy form' => ["250-mx\r\n250 AUTH=LOGIN PLAIN\r\n", true, ['LOGIN', 'PLAIN']],
            'both forms' => ["250-mx\r\n250-AUTH LOGIN\r\n250 AUTH=PLAIN LOGIN\r\n", true, ['LOGIN', 'PLAIN']],
            'several AUTH lines' => ["250-mx\r\n250-AUTH LOGIN\r\n250 AUTH XOAUTH2 CRAM-MD5\r\n", true, [
                'LOGIN',
                'XOAUTH2',
                'CRAM-MD5',
            ]],
            'AUTH without methods' => ["250-mx\r\n250 AUTH\r\n", true, []],
            'line feed only' => ["250-mx\n250 AUTH PLAIN\n", true, ['PLAIN']],
            'greeting is not a capability' => ["250 AUTH PLAIN\r\n", false, []],
            'other keyword' => ["250-mx\r\n250 AUTHENTICATE PLAIN\r\n", false, []],
            'empty' => ['', false, []],
        ];
    }

    /**
     * @param list<string> $methods
     */
    #[DataProvider('replies')]
    public function testParsesTheAuthLines(string $reply, bool $announces, array $methods): void
    {
        $capabilities = SmtpCapabilities::fromEhloReply(reply: $reply);

        $this->assertSame($announces, $capabilities->announcesAuth);
        $this->assertSame($methods, $capabilities->authMethods);
    }

    public function testSupportsOnlyTheAnnouncedMethods(): void
    {
        $capabilities = SmtpCapabilities::fromEhloReply(reply: "250-mx\r\n250 AUTH LOGIN CRAM-MD5\r\n");

        $this->assertTrue($capabilities->supports(method: SmtpAuthMethodEnum::LOGIN));
        $this->assertFalse($capabilities->supports(method: SmtpAuthMethodEnum::PLAIN));
        $this->assertFalse($capabilities->supports(method: SmtpAuthMethodEnum::XOAUTH2));
    }
}
