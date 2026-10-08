<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\clock\FixedClock;
use actra\yuf\mailer\attachment\MailerStringAttachment;
use actra\yuf\mailer\GraphMailer;
use actra\yuf\mailer\MailerException;
use actra\yuf\mailer\MicrosoftClientCredentialsTokenProvider;
use actra\yuf\mailer\OAuthTokenProvider;
use actra\yuf\mailer\TextMail;
use actra\yuf\tests\Double\api\EchoedRequest;
use actra\yuf\tests\Double\api\ScriptedHttpServer;
use actra\yuf\tests\Double\clock\AdjustableClock;
use actra\yuf\tests\Double\mailer\FixedMimeIdGenerator;
use actra\yuf\tests\Double\mailer\FixedOAuthTokenProvider;
use actra\yuf\tests\Double\mailer\FixedServerNameResolver;
use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The delivery through the Graph API against a local server that plays Graph (no network).
 */
final class GraphMailerTest extends TestCase
{
    private const string TOKEN = 'graph-access-token';
    private const string SEND_PATH = '/v1.0/users/noreply%40example.com/sendMail';

    private ScriptedHttpServer $server;

    #[Override]
    protected function setUp(): void
    {
        $this->server = new ScriptedHttpServer();
        $this->server->respond(path: GraphMailerTest::SEND_PATH, status: 202, body: '');
    }

    public function testSendsTheMimeMessageAsBase64WithTheBearerToken(): void
    {
        $mail = $this->mail();
        $mail->addCc(inputEmail: 'cc@example.com', inputName: 'Carbon');
        $mail->addBcc(inputEmail: 'bcc@example.com');
        $mail->addAttachment(
            mailerAttachment: new MailerStringAttachment(
                contentString: 'Hello attachment',
                fileName: 'hello.txt',
                type: '',
            ),
        );

        $mail->send(abstractMailer: $this->mailer());

        $requests = $this->server->requests();
        $this->assertCount(1, $requests);
        $this->assertSame('POST', $requests[0]->method);
        $this->assertSame(GraphMailerTest::SEND_PATH, $requests[0]->uri);
        $this->assertSame('text/plain; charset=utf-8', $requests[0]->header('Content-Type'));
        $this->assertSame('Bearer ' . GraphMailerTest::TOKEN, $requests[0]->header('Authorization'));
        $message = $this->decodedMessage(request: $requests[0]);
        $this->assertSame(
            [
                'Date: Thu, 08 Oct 2026 12:00:00 +0000',
                'From: From <from@example.com>',
                'To: To <to@example.com>',
                'Cc: Carbon <cc@example.com>',
                'Bcc: bcc@example.com',
                'Reply-To: From <from@example.com>',
                'Subject: Subj',
                'Message-ID: <ID@mail.example.com>',
            ],
            array_slice(array: $this->headerLines(message: $message), offset: 0, length: 8),
        );
        $this->assertStringContainsString('Content-Disposition: attachment; filename=hello.txt', $message);
        $this->assertStringContainsString(base64_encode(string: 'Hello attachment'), $message);
        $this->assertStringContainsString("\r\n\r\n", $message);
    }

    public function testMailboxIsEncodedInTheUrl(): void
    {
        $path = '/v1.0/users/a%2Fb%20c%3Fd%23e%40example.com/sendMail';
        $this->server->respond(path: $path, status: 202, body: '');

        $this->mail()->send(abstractMailer: $this->mailer(mailbox: 'a/b c?d#e@example.com'));

        $this->assertSame($path, $this->server->request(index: 0)->uri);
    }

    public function testBaseUrlWithTrailingSlashIsAccepted(): void
    {
        $this->mail()->send(abstractMailer: $this->mailer(baseUrl: $this->server->url('/v1.0/')));

        $this->assertSame(GraphMailerTest::SEND_PATH, $this->server->request(index: 0)->uri);
    }

    public function testEveryMailAsksTheProviderForTheToken(): void
    {
        $tokenProvider = new FixedOAuthTokenProvider(accessToken: GraphMailerTest::TOKEN);
        $mailer = $this->mailer(tokenProvider: $tokenProvider);

        $this->mail()->send(abstractMailer: $mailer);
        $this->mail()->send(abstractMailer: $mailer);

        $this->assertSame(2, $tokenProvider->requests);
        $this->assertCount(2, $this->server->requests());
    }

    public function testTwoMailsNeedOneTokenRequestWithTheClientCredentialsProvider(): void
    {
        $this->server->respond(
            path: '/tenant-1/oauth2/v2.0/token',
            status: 200,
            body: '{"token_type":"Bearer","expires_in":3600,"access_token":"from-the-flow"}',
        );
        $tokenProvider = new MicrosoftClientCredentialsTokenProvider(
            tenantId: 'tenant-1',
            clientId: 'client-1',
            clientSecret: 'secret',
            authorityUrl: $this->server->url(''),
            clock: new AdjustableClock(now: new DateTimeImmutable(datetime: '2026-10-08 12:00:00 UTC')),
        );
        $mailer = $this->mailer(tokenProvider: $tokenProvider);

        $this->mail()->send(abstractMailer: $mailer);
        $this->mail()->send(abstractMailer: $mailer);

        $requests = $this->server->requests();
        $this->assertSame(
            ['/tenant-1/oauth2/v2.0/token', GraphMailerTest::SEND_PATH, GraphMailerTest::SEND_PATH],
            array_map(callback: static fn(EchoedRequest $request): string => $request->uri, array: $requests),
        );
        $this->assertSame('Bearer from-the-flow', $this->server->request(index: 1)->header('Authorization'));
        $this->assertSame('Bearer from-the-flow', $this->server->request(index: 2)->header('Authorization'));
    }

    public function testErrorOfGraphGivesStatusAndCode(): void
    {
        $this->server->respond(
            path: GraphMailerTest::SEND_PATH,
            status: 403,
            body: '{"error":{"code":"ErrorAccessDenied","message":"Access is denied. Check credentials."}}',
        );

        try {
            $this->mail()->send(abstractMailer: $this->mailer());
            self::fail('A MailerException was expected.');
        } catch (MailerException $exception) {
            $this->assertSame(
                'The Microsoft Graph API did not accept the message: HTTP status 403:'
                    . ' ErrorAccessDenied: Access is denied. Check credentials.',
                $exception->getMessage(),
            );
            $this->assertStringNotContainsString(GraphMailerTest::TOKEN, $exception->getMessage());
        }
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function failedResponses(): array
    {
        return [
            'no JSON' => [500, 'Bad gateway', 'HTTP status 500.'],
            'JSON without error' => [400, '{"value":1}', 'HTTP status 400.'],
            'error is no object' => [400, '{"error":"x"}', 'HTTP status 400.'],
            'error code only' => [404, '{"error":{"code":"ResourceNotFound"}}', 'HTTP status 404: ResourceNotFound'],
            'status 200' => [200, '{}', 'HTTP status 200.'],
            'redirect' => [302, '', 'HTTP status 302.'],
        ];
    }

    #[DataProvider('failedResponses')]
    public function testAnythingBesidesAcceptedIsAnError(int $status, string $body, string $expectedEnd): void
    {
        $this->server->respond(path: GraphMailerTest::SEND_PATH, status: $status, body: $body);

        $this->expectException(MailerException::class);
        $this->expectExceptionMessageIsOrContains(
            'The Microsoft Graph API did not accept the message: ' . $expectedEnd,
        );

        $this->mail()->send(abstractMailer: $this->mailer());
    }

    public function testTransferFailureIsReported(): void
    {
        $this->expectException(MailerException::class);
        $this->expectExceptionMessageMatches('/^The request to the Microsoft Graph API failed: /');

        $this->mail()->send(abstractMailer: $this->mailer(baseUrl: 'http://127.0.0.1:1/v1.0'));
    }

    public function testMessageThatIsTooLargeIsNotSent(): void
    {
        $tokenProvider = new FixedOAuthTokenProvider(accessToken: GraphMailerTest::TOKEN);
        $mail = $this->mail();
        // The attachment is Base64 encoded in the MIME message and the message again in the request
        $mail->addAttachment(
            mailerAttachment: new MailerStringAttachment(
                contentString: str_repeat(string: 'a', times: 2400000),
                fileName: 'big.txt',
                type: '',
            ),
        );

        try {
            $mail->send(abstractMailer: $this->mailer(tokenProvider: $tokenProvider));
            self::fail('A MailerException was expected.');
        } catch (MailerException $exception) {
            $this->assertStringContainsString('too large for the Microsoft Graph API', $exception->getMessage());
            $this->assertStringContainsString('the limit is 4194304 bytes (4 MB)', $exception->getMessage());
            $this->assertStringContainsString('Larger attachments are not supported', $exception->getMessage());
        }
        $this->assertSame([], $this->server->requests());
        $this->assertSame(0, $tokenProvider->requests);
    }

    public function testMessageBelowTheLimitIsSent(): void
    {
        $mail = $this->mail();
        $mail->addAttachment(
            mailerAttachment: new MailerStringAttachment(
                contentString: str_repeat(string: 'a', times: 2100000),
                fileName: 'big.txt',
                type: '',
            ),
        );

        $mail->send(abstractMailer: $this->mailer());

        $this->assertCount(1, $this->server->requests());
        $this->assertLessThanOrEqual(4194304, strlen(string: $this->server->request(index: 0)->body));
    }

    public function testTokenThatCannotBeSentIsNotShown(): void
    {
        try {
            $this->mail()->send(
                abstractMailer: $this->mailer(tokenProvider: new FixedOAuthTokenProvider(accessToken: 'bad token')),
            );
            self::fail('A MailerException was expected.');
        } catch (MailerException $exception) {
            $this->assertStringNotContainsString('bad token', $exception->getMessage());
        }
        $this->assertSame([], $this->server->requests());
    }

    public function testTokenIsNotSentOverPlainHttpToAnotherHost(): void
    {
        $this->expectException(MailerException::class);
        $this->expectExceptionMessageIsOrContains('the Graph URL must use HTTPS');

        $this->mail()->send(abstractMailer: $this->mailer(baseUrl: 'http://graph.example.com/v1.0'));
    }

    public function testHeaderLayoutForGraph(): void
    {
        $mailer = $this->mailer();

        $this->assertTrue($mailer->headerHasTo());
        $this->assertTrue($mailer->headerHasSubject());
        $this->assertTrue($mailer->headerHasBcc());
        $this->assertSame(998, $mailer->getMaxLineLength());
    }

    public function testEmptyMailboxIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->mailer(mailbox: '');
    }

    private function mailer(
        string $mailbox = 'noreply@example.com',
        ?string $baseUrl = null,
        ?OAuthTokenProvider $tokenProvider = null,
    ): GraphMailer {
        return new GraphMailer(
            serverAddress: '192.0.2.1',
            senderMailbox: $mailbox,
            oAuthTokenProvider: $tokenProvider ?? new FixedOAuthTokenProvider(accessToken: GraphMailerTest::TOKEN),
            graphBaseUrl: $baseUrl ?? $this->server->url('/v1.0'),
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '2026-10-08 12:00:00 UTC')),
            mimeIdGenerator: new FixedMimeIdGenerator(),
            serverNameResolver: new FixedServerNameResolver(),
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

    private function decodedMessage(EchoedRequest $request): string
    {
        $message = base64_decode(string: $request->body, strict: true);
        $this->assertIsString($message);

        return $message;
    }

    /**
     * @return list<string>
     */
    private function headerLines(string $message): array
    {
        return explode(separator: "\r\n", string: explode(separator: "\r\n\r\n", string: $message, limit: 2)[0]);
    }
}
