<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\clock\FixedClock;
use actra\yuf\mailer\MailerException;
use actra\yuf\mailer\OAuthTokenProvider;
use actra\yuf\mailer\SmtpAuthMethodEnum;
use actra\yuf\mailer\SmtpMailer;
use actra\yuf\mailer\TextMail;
use actra\yuf\tests\Double\mailer\FakeSmtpTransport;
use actra\yuf\tests\Double\mailer\FixedMimeIdGenerator;
use actra\yuf\tests\Double\mailer\FixedOAuthTokenProvider;
use actra\yuf\tests\Double\mailer\FixedServerNameResolver;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The choice of the authentication method and the dialogue of `AUTH PLAIN` and `AUTH XOAUTH2` with a scripted server
 * (`AUTH LOGIN` is covered by `SmtpMailerTest`).
 */
final class SmtpAuthenticationTest extends TestCase
{
    private const string PASSWORD = 'secret-pass';
    private const string TOKEN = 'ya29.token';
    private const string PLAIN_PAYLOAD = 'AHVzZXIAc2VjcmV0LXBhc3M=';
    private const string XOAUTH2_PAYLOAD = 'dXNlcj11c2VyQGV4YW1wbGUuY29tAWF1dGg9QmVhcmVyIHlhMjkudG9rZW4BAQ==';
    private const string LOGIN_PASSWORD_PAYLOAD = 'c2VjcmV0LXBhc3M=';

    public function testPlainIsChosenBeforeLoginAndSendsTheInitialResponse(): void
    {
        $transport = new FakeSmtpTransport(
            replies: SmtpAuthenticationTest::replies(ehlo: "250-mx\r\n250 AUTH LOGIN PLAIN\r\n"),
        );
        $mailer = $this->mailer(transport: $transport, userName: 'user');

        $this->mail()->send(abstractMailer: $mailer);

        $this->assertSame(
            [
                'EHLO mail.example.com',
                'AUTH PLAIN ' . SmtpAuthenticationTest::PLAIN_PAYLOAD,
                'MAIL FROM: <send@example.com>',
            ],
            array_slice(array: $transport->writtenLines(), offset: 0, length: 3),
        );
        $this->assertSame(
            ['EHLO mail.example.com', "250-mx\r\n", "250 AUTH LOGIN PLAIN\r\n", 'AUTH PLAIN (hidden)', "235 ok\r\n"],
            array_slice(array: $mailer->log, offset: 1, length: 5),
        );
    }

    public function testLoginIsChosenWhenPlainIsNotAnnounced(): void
    {
        $transport = new FakeSmtpTransport(
            replies: SmtpAuthenticationTest::replies(
                ehlo: "250-mx\r\n250 AUTH CRAM-MD5 LOGIN\r\n",
                auth: SmtpAuthenticationTest::loginReplies(),
            ),
        );

        $this->mail()->send(abstractMailer: $this->mailer(transport: $transport, userName: 'user'));

        $this->assertSame(
            ['EHLO mail.example.com', 'AUTH LOGIN', 'dXNlcg==', SmtpAuthenticationTest::LOGIN_PASSWORD_PAYLOAD],
            array_slice(array: $transport->writtenLines(), offset: 0, length: 4),
        );
    }

    public function testServerWithoutAuthLineGetsLoginAsBefore(): void
    {
        $transport = new FakeSmtpTransport(
            replies: SmtpAuthenticationTest::replies(ehlo: "250 mx\r\n", auth: SmtpAuthenticationTest::loginReplies()),
        );

        $this->mail()->send(abstractMailer: $this->mailer(transport: $transport, userName: 'user'));

        $this->assertSame('AUTH LOGIN', $transport->writtenLines()[1] ?? '');
    }

    public function testAnnouncedMethodsAreReadAfterStartTls(): void
    {
        $transport = new FakeSmtpTransport(
            replies: [
                "220 mx ready\r\n",
                "250-mx\r\n250-STARTTLS\r\n250 AUTH LOGIN\r\n",
                "220 go ahead\r\n",
                "250-mx\r\n250 AUTH PLAIN\r\n",
                ...array_slice(array: SmtpAuthenticationTest::replies(ehlo: ''), offset: 2),
            ],
        );

        $this->mail()->send(abstractMailer: $this->mailer(transport: $transport, userName: 'user', useTls: true));

        $this->assertContains('AUTH PLAIN ' . SmtpAuthenticationTest::PLAIN_PAYLOAD, $transport->writtenLines());
        $this->assertNotContains('AUTH LOGIN', $transport->writtenLines());
        $this->assertSame(
            [
                'write STARTTLS',
                'tls',
                'write EHLO mail.example.com',
                'write AUTH PLAIN ' . SmtpAuthenticationTest::PLAIN_PAYLOAD,
            ],
            array_slice(array: $transport->events, offset: 2, length: 4),
        );
    }

    public function testFixedLoginIsUsedAlthoughPlainIsAnnounced(): void
    {
        $transport = new FakeSmtpTransport(
            replies: SmtpAuthenticationTest::replies(
                ehlo: "250-mx\r\n250 AUTH PLAIN LOGIN\r\n",
                auth: SmtpAuthenticationTest::loginReplies(),
            ),
        );
        $mailer = $this->mailer(transport: $transport, userName: 'user', authMethod: SmtpAuthMethodEnum::LOGIN);

        $this->mail()->send(abstractMailer: $mailer);

        $this->assertSame('AUTH LOGIN', $transport->writtenLines()[1] ?? '');
    }

    public function testFixedMethodThatTheServerDoesNotAnnounceAborts(): void
    {
        $transport = new FakeSmtpTransport(replies: ["220 mx ready\r\n", "250-mx\r\n250 AUTH LOGIN\r\n"]);
        $mailer = $this->mailer(transport: $transport, userName: 'user', authMethod: SmtpAuthMethodEnum::PLAIN);

        try {
            $this->mail()->send(abstractMailer: $mailer);
            self::fail('The delivery must be aborted.');
        } catch (MailerException $exception) {
            $this->assertSame(
                'The SMTP server does not announce the authentication method PLAIN (announced: LOGIN).',
                $exception->getMessage(),
            );
        }

        $this->assertSame(['EHLO mail.example.com'], $transport->writtenLines());
        $this->assertSame('close', $transport->lastEvent());
    }

    public function testFixedMethodWithAServerThatAnnouncesNothingAborts(): void
    {
        $transport = new FakeSmtpTransport(replies: ["220 mx ready\r\n", "250 mx\r\n"]);
        $mailer = $this->mailer(transport: $transport, userName: 'user', authMethod: SmtpAuthMethodEnum::PLAIN);

        try {
            $this->mail()->send(abstractMailer: $mailer);
            self::fail('The delivery must be aborted.');
        } catch (MailerException $exception) {
            $this->assertSame(
                'The SMTP server does not announce the authentication method PLAIN (announced: none).',
                $exception->getMessage(),
            );
        }
    }

    public function testServerWithoutPlainAndLoginAborts(): void
    {
        $transport = new FakeSmtpTransport(replies: ["220 mx ready\r\n", "250-mx\r\n250 AUTH CRAM-MD5 XOAUTH2\r\n"]);

        try {
            $this->mail()->send(abstractMailer: $this->mailer(transport: $transport, userName: 'user'));
            self::fail('The delivery must be aborted.');
        } catch (MailerException $exception) {
            $this->assertSame(
                'The SMTP server announces none of the authentication methods PLAIN, LOGIN'
                    . ' (announced: CRAM-MD5, XOAUTH2).',
                $exception->getMessage(),
            );
        }

        $this->assertSame(['EHLO mail.example.com'], $transport->writtenLines());
    }

    public function testPlainRejectsANulCharacterInTheCredentials(): void
    {
        $transport = new FakeSmtpTransport(replies: ["220 mx ready\r\n", "250-mx\r\n250 AUTH PLAIN\r\n"]);

        try {
            $this->mail()->send(abstractMailer: $this->mailer(transport: $transport, userName: "us\0er"));
            self::fail('The delivery must be aborted.');
        } catch (MailerException $exception) {
            $this->assertSame(
                'The user name and the password must not contain a NUL character for AUTH PLAIN.',
                $exception->getMessage(),
            );
        }

        $this->assertSame(['EHLO mail.example.com'], $transport->writtenLines());
    }

    public function testRejectedPlainCredentialsAreNeitherInTheExceptionNorInTheLog(): void
    {
        $transport = new FakeSmtpTransport(
            replies: ["220 mx ready\r\n", "250-mx\r\n250 AUTH PLAIN\r\n", "535 5.7.8 bad credentials\r\n"],
        );
        $mailer = $this->mailer(transport: $transport, userName: 'user');

        try {
            $this->mail()->send(abstractMailer: $mailer);
            self::fail('The delivery must be aborted.');
        } catch (MailerException $exception) {
            $this->assertSame(
                'Unexpected answer of the SMTP server to AUTH PLAIN: code 535 instead of 235.',
                $exception->getMessage(),
            );
        }

        $this->assertNoCredentials(message: $exception->getMessage(), mailer: $mailer);
    }

    public function testXOAuth2SendsTheTokenAsInitialResponse(): void
    {
        $transport = new FakeSmtpTransport(
            replies: SmtpAuthenticationTest::replies(ehlo: "250-mx\r\n250 AUTH PLAIN XOAUTH2\r\n"),
        );
        $provider = new FixedOAuthTokenProvider(accessToken: SmtpAuthenticationTest::TOKEN);
        $mailer = $this->mailer(transport: $transport, userName: 'user@example.com', tokenProvider: $provider);

        $this->mail()->send(abstractMailer: $mailer);

        $this->assertSame(
            [
                'EHLO mail.example.com',
                'AUTH XOAUTH2 ' . SmtpAuthenticationTest::XOAUTH2_PAYLOAD,
                'MAIL FROM: <send@example.com>',
            ],
            array_slice(array: $transport->writtenLines(), offset: 0, length: 3),
        );
        $this->assertSame(1, $provider->requests);
        $this->assertSame('AUTH XOAUTH2 (hidden)', $mailer->log[4] ?? '');
        $this->assertNoCredentials(message: '', mailer: $mailer);
    }

    public function testXOAuth2IsChosenFromAServerThatAnnouncesNothing(): void
    {
        $transport = new FakeSmtpTransport(replies: SmtpAuthenticationTest::replies(ehlo: "250 mx\r\n"));
        $provider = new FixedOAuthTokenProvider(accessToken: SmtpAuthenticationTest::TOKEN);

        $mailer = $this->mailer(transport: $transport, userName: 'user@example.com', tokenProvider: $provider);

        $this->mail()->send(abstractMailer: $mailer);

        $this->assertSame(
            'AUTH XOAUTH2 ' . SmtpAuthenticationTest::XOAUTH2_PAYLOAD,
            $transport->writtenLines()[1] ?? '',
        );
    }

    public function testXOAuth2WithAServerThatDoesNotAnnounceItAbortsBeforeTheTokenIsRequested(): void
    {
        $transport = new FakeSmtpTransport(replies: ["220 mx ready\r\n", "250-mx\r\n250 AUTH PLAIN LOGIN\r\n"]);
        $provider = new FixedOAuthTokenProvider(accessToken: SmtpAuthenticationTest::TOKEN);
        $mailer = $this->mailer(transport: $transport, userName: 'user@example.com', tokenProvider: $provider);

        try {
            $this->mail()->send(abstractMailer: $mailer);
            self::fail('The delivery must be aborted.');
        } catch (MailerException $exception) {
            $this->assertSame(
                'The SMTP server announces none of the authentication methods XOAUTH2'
                    . ' (announced: PLAIN, LOGIN).',
                $exception->getMessage(),
            );
        }

        $this->assertSame(0, $provider->requests);
    }

    public function testXOAuth2FailureAnswersTheJsonChallengeWithAnEmptyLine(): void
    {
        $challenge = base64_encode(string: '{"status":"401","schemes":"Bearer","scope":"https://mail.google.com/"}');
        $transport = new FakeSmtpTransport(
            replies: [
                "220 mx ready\r\n",
                "250-mx\r\n250 AUTH XOAUTH2\r\n",
                '334 ' . $challenge . "\r\n",
                "535-5.7.8 Username and Password not accepted\r\n535 5.7.8 more\r\n",
            ],
        );
        $provider = new FixedOAuthTokenProvider(accessToken: SmtpAuthenticationTest::TOKEN);
        $mailer = $this->mailer(transport: $transport, userName: 'user@example.com', tokenProvider: $provider);

        try {
            $this->mail()->send(abstractMailer: $mailer);
            self::fail('The delivery must be aborted.');
        } catch (MailerException $exception) {
            $this->assertSame(
                'The SMTP server rejected the authentication with XOAUTH2: code 535'
                    . ' (check the access token and the mailbox).',
                $exception->getMessage(),
            );
        }

        $this->assertSame(
            ['EHLO mail.example.com', 'AUTH XOAUTH2 ' . SmtpAuthenticationTest::XOAUTH2_PAYLOAD, ''],
            $transport->writtenLines(),
        );
        $this->assertSame('close', $transport->lastEvent());
        $this->assertNoCredentials(message: $exception->getMessage(), mailer: $mailer);
    }

    public function testXOAuth2WithAnImmediateErrorCode(): void
    {
        $transport = new FakeSmtpTransport(
            replies: [
                "220 mx ready\r\n",
                "250-mx\r\n250 AUTH XOAUTH2\r\n",
                "535 5.7.3 Authentication unsuccessful\r\n",
            ],
        );
        $provider = new FixedOAuthTokenProvider(accessToken: SmtpAuthenticationTest::TOKEN);
        $mailer = $this->mailer(transport: $transport, userName: 'user@example.com', tokenProvider: $provider);

        try {
            $this->mail()->send(abstractMailer: $mailer);
            self::fail('The delivery must be aborted.');
        } catch (MailerException $exception) {
            $this->assertStringContainsString('code 535', $exception->getMessage());
        }

        $this->assertCount(2, $transport->writtenLines());
    }

    public function testEmptyAccessTokenAbortsWithoutSendingAnything(): void
    {
        $transport = new FakeSmtpTransport(replies: ["220 mx ready\r\n", "250-mx\r\n250 AUTH XOAUTH2\r\n"]);
        $mailer = $this->mailer(
            transport: $transport,
            userName: 'user@example.com',
            tokenProvider: new FixedOAuthTokenProvider(accessToken: ''),
        );

        try {
            $this->mail()->send(abstractMailer: $mailer);
            self::fail('The delivery must be aborted.');
        } catch (MailerException $exception) {
            $this->assertSame('The OAuth token provider returned an empty access token.', $exception->getMessage());
        }

        $this->assertSame(['EHLO mail.example.com'], $transport->writtenLines());
    }

    public function testNoTokenIsRequestedWithoutAUserName(): void
    {
        $transport = new FakeSmtpTransport(replies: SmtpAuthenticationTest::replies(ehlo: "250 mx\r\n", auth: []));
        $provider = new FixedOAuthTokenProvider(accessToken: SmtpAuthenticationTest::TOKEN);

        $mailer = $this->mailer(transport: $transport, userName: '', tokenProvider: $provider);

        $this->mail()->send(abstractMailer: $mailer);

        $this->assertSame(0, $provider->requests);
    }

    public function testXOAuth2NeedsATokenProvider(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The authentication method XOAUTH2 needs an OAuthTokenProvider.');

        $this->mailer(
            transport: new FakeSmtpTransport(replies: []),
            userName: 'user',
            authMethod: SmtpAuthMethodEnum::XOAUTH2,
        );
    }

    public function testATokenProviderExcludesPasswordMethods(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs(
            'An OAuthTokenProvider can only be used with the authentication method XOAUTH2.',
        );

        $this->mailer(
            transport: new FakeSmtpTransport(replies: []),
            userName: 'user',
            authMethod: SmtpAuthMethodEnum::PLAIN,
            tokenProvider: new FixedOAuthTokenProvider(accessToken: SmtpAuthenticationTest::TOKEN),
        );
    }

    private function assertNoCredentials(string $message, SmtpMailer $mailer): void
    {
        $text = $message . implode(separator: '', array: $mailer->log);
        $credentials = [
            SmtpAuthenticationTest::PASSWORD,
            SmtpAuthenticationTest::TOKEN,
            SmtpAuthenticationTest::PLAIN_PAYLOAD,
            SmtpAuthenticationTest::XOAUTH2_PAYLOAD,
        ];
        foreach ($credentials as $credential) {
            $this->assertStringNotContainsString($credential, $text);
        }
    }

    private function mailer(
        FakeSmtpTransport $transport,
        string $userName,
        bool $useTls = false,
        ?SmtpAuthMethodEnum $authMethod = null,
        ?OAuthTokenProvider $tokenProvider = null,
    ): SmtpMailer {
        return new SmtpMailer(
            serverAddress: '192.0.2.1',
            hostName: 'smtp.example.com',
            smtpUserName: $userName,
            smtpPassword: SmtpAuthenticationTest::PASSWORD,
            serverNameCache: null,
            port: 587,
            useTls: $useTls,
            transport: $transport,
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '2026-10-08 12:00:00 UTC')),
            mimeIdGenerator: new FixedMimeIdGenerator(),
            serverNameResolver: new FixedServerNameResolver(),
            authMethod: $authMethod,
            oAuthTokenProvider: $tokenProvider,
        );
    }

    private function mail(): TextMail
    {
        return new TextMail(
            senderEmail: 'send@example.com',
            fromEmail: 'from@example.com',
            fromName: 'From',
            toEmail: 'to@example.com',
            toName: 'To',
            subject: 'Subj',
            textBody: 'Hello',
        );
    }

    /**
     * @return list<string>
     */
    private static function loginReplies(): array
    {
        return ["334 VXNlcm5hbWU6\r\n", "334 UGFzc3dvcmQ6\r\n", "235 ok\r\n"];
    }

    /**
     * @param list<string>|null $auth the answers to the authentication, `null`: one `235`
     *
     * @return list<string> the answers of a server that accepts a delivery to one recipient
     */
    private static function replies(string $ehlo, ?array $auth = null): array
    {
        return [
            "220 mx ready\r\n",
            $ehlo,
            ...($auth ?? ["235 ok\r\n"]),
            "250 ok\r\n",
            "250 ok\r\n",
            "354 go\r\n",
            "250 queued\r\n",
            "221 bye\r\n",
        ];
    }
}
