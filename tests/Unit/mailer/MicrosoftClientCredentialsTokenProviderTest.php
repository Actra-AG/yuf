<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\common\FileCache;
use actra\yuf\mailer\MailerException;
use actra\yuf\mailer\MicrosoftClientCredentialsTokenProvider;
use actra\yuf\tests\Double\api\ScriptedHttpServer;
use actra\yuf\tests\Double\clock\AdjustableClock;
use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The client credentials flow against a local server that plays the identity platform (no network).
 */
final class MicrosoftClientCredentialsTokenProviderTest extends TestCase
{
    private const string SECRET = 'client-secret-value';
    private const string TOKEN_PATH = '/tenant-1/oauth2/v2.0/token';

    private ScriptedHttpServer $server;
    private AdjustableClock $clock;

    #[Override]
    protected function setUp(): void
    {
        $this->server = new ScriptedHttpServer();
        $this->clock = new AdjustableClock(now: new DateTimeImmutable(datetime: '2026-10-08 12:00:00 UTC'));
    }

    public function testRequestsTheTokenWithTheClientCredentialsFlow(): void
    {
        $this->server->respond(
            path: MicrosoftClientCredentialsTokenProviderTest::TOKEN_PATH,
            status: 200,
            body: '{"token_type":"Bearer","expires_in":3599,"access_token":"token-1"}',
        );

        $token = $this->provider()->getAccessToken();

        $this->assertSame('token-1', $token);
        $requests = $this->server->requests();
        $this->assertCount(1, $requests);
        $this->assertSame('POST', $requests[0]->method);
        $this->assertSame(MicrosoftClientCredentialsTokenProviderTest::TOKEN_PATH, $requests[0]->uri);
        $this->assertSame('application/x-www-form-urlencoded; charset=utf-8', $requests[0]->header('Content-Type'));
        $this->assertSame('application/json', $requests[0]->header('Accept'));
        $this->assertFalse($requests[0]->hasHeader('Authorization'));
        $this->assertSame(
            'client_id=client-1&client_secret=' . MicrosoftClientCredentialsTokenProviderTest::SECRET
                . '&scope=https%3A%2F%2Fgraph.microsoft.com%2F.default&grant_type=client_credentials',
            $requests[0]->body,
        );
    }

    public function testScopeIsPassedOn(): void
    {
        $this->respondWithToken(token: 'token-1', expiresIn: 3600);

        $this->provider(scope: 'https://outlook.office365.com/.default')->getAccessToken();

        $this->assertStringContainsString(
            'scope=https%3A%2F%2Foutlook.office365.com%2F.default&',
            $this->server->request(index: 0)->body,
        );
    }

    public function testTrailingSlashOfTheAuthorityUrlIsIgnored(): void
    {
        $this->respondWithToken(token: 'token-1', expiresIn: 3600);

        $provider = new MicrosoftClientCredentialsTokenProvider(
            tenantId: 'tenant-1',
            clientId: 'client-1',
            clientSecret: MicrosoftClientCredentialsTokenProviderTest::SECRET,
            tokenCache: null,
            authorityUrl: $this->server->url('/'),
            clock: $this->clock,
        );

        $this->assertSame('token-1', $provider->getAccessToken());
        $this->assertSame(
            MicrosoftClientCredentialsTokenProviderTest::TOKEN_PATH,
            $this->server->request(index: 0)->uri,
        );
    }

    public function testTokenIsKeptUntilOneMinuteBeforeItExpires(): void
    {
        $this->respondWithToken(token: 'token-1', expiresIn: 3600);
        $provider = $this->provider();

        $this->assertSame('token-1', $provider->getAccessToken());
        $this->clock->advanceSeconds(seconds: 3539);
        $this->assertSame('token-1', $provider->getAccessToken());
        $this->assertCount(1, $this->server->requests());

        $this->respondWithToken(token: 'token-2', expiresIn: 3600);
        $this->clock->advanceSeconds(seconds: 1);

        $this->assertSame('token-2', $provider->getAccessToken());
        $this->assertCount(2, $this->server->requests());
    }

    public function testCachedTokenServesTheNextRequestUntilOneMinuteBeforeItExpires(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-token-cache-'
            . bin2hex(string: random_bytes(length: 8));
        $tokenCache = new FileCache(directory: $directory, clock: $this->clock);
        $this->respondWithToken(token: 'token-1', expiresIn: 3600);

        try {
            $this->assertSame('token-1', $this->provider(tokenCache: $tokenCache)->getAccessToken());
            $this->clock->advanceSeconds(seconds: 3539);
            $this->assertSame('token-1', $this->provider(tokenCache: $tokenCache)->getAccessToken());
            $this->assertCount(1, $this->server->requests());

            $this->respondWithToken(token: 'token-2', expiresIn: 3600);
            $this->clock->advanceSeconds(seconds: 1);
            $this->assertSame('token-2', $this->provider(tokenCache: $tokenCache)->getAccessToken());
            $this->assertCount(2, $this->server->requests());
        } finally {
            $files = glob(pattern: $directory . DIRECTORY_SEPARATOR . '*');
            foreach ($files === false ? [] : $files as $file) {
                unlink(filename: $file);
            }
            rmdir(directory: $directory);
        }
    }

    public function testTokenThatLivesNoLongerThanTheMarginIsNotKept(): void
    {
        $this->respondWithToken(token: 'token-1', expiresIn: 60);
        $provider = $this->provider();

        $provider->getAccessToken();
        $provider->getAccessToken();

        $this->assertCount(2, $this->server->requests());
    }

    public function testExpiresInAsDigitsIsAccepted(): void
    {
        $this->server->respond(
            path: MicrosoftClientCredentialsTokenProviderTest::TOKEN_PATH,
            status: 200,
            body: '{"token_type":"bearer","expires_in":"3600","access_token":"token-1"}',
        );
        $provider = $this->provider();

        $provider->getAccessToken();
        $provider->getAccessToken();

        $this->assertCount(1, $this->server->requests());
    }

    public function testErrorResponseGivesStatusAndOAuthError(): void
    {
        $this->server->respond(
            path: MicrosoftClientCredentialsTokenProviderTest::TOKEN_PATH,
            status: 401,
            body: '{"error":"invalid_client",'
                . '"error_description":"AADSTS7000215: Invalid client secret.\r\nTrace ID: 1"}',
        );

        try {
            $this->provider()->getAccessToken();
            MicrosoftClientCredentialsTokenProviderTest::fail('A MailerException was expected.');
        } catch (MailerException $exception) {
            $this->assertSame(
                'The request for the OAuth access token failed with HTTP status 401: invalid_client:'
                    . ' AADSTS7000215: Invalid client secret. Trace ID: 1',
                $exception->getMessage(),
            );
            $this->assertStringNotContainsString(
                MicrosoftClientCredentialsTokenProviderTest::SECRET,
                $exception->getMessage(),
            );
        }
    }

    public function testFailedRequestIsNotCached(): void
    {
        $this->server->respond(
            path: MicrosoftClientCredentialsTokenProviderTest::TOKEN_PATH,
            status: 500,
            body: 'not json',
        );
        $provider = $this->provider();
        try {
            $provider->getAccessToken();
            MicrosoftClientCredentialsTokenProviderTest::fail('A MailerException was expected.');
        } catch (MailerException $exception) {
            $this->assertSame(
                'The request for the OAuth access token failed with HTTP status 500.',
                $exception->getMessage(),
            );
        }
        $this->respondWithToken(token: 'token-1', expiresIn: 3600);

        $this->assertSame('token-1', $provider->getAccessToken());
    }

    public function testTextOfTheErrorIsCleanedAndCut(): void
    {
        $this->server->respond(
            path: MicrosoftClientCredentialsTokenProviderTest::TOKEN_PATH,
            status: 400,
            body: json_encode(
                value: ['error' => "bad\x00\n\"code\"", 'error_description' => str_repeat(string: 'x', times: 1000)],
                flags: JSON_THROW_ON_ERROR,
            ),
        );

        try {
            $this->provider()->getAccessToken();
            MicrosoftClientCredentialsTokenProviderTest::fail('A MailerException was expected.');
        } catch (MailerException $exception) {
            $this->assertSame(
                'The request for the OAuth access token failed with HTTP status 400: bad "code": '
                    . str_repeat(string: 'x', times: 300),
                $exception->getMessage(),
            );
        }
    }

    public function testTransferFailureIsReported(): void
    {
        $provider = new MicrosoftClientCredentialsTokenProvider(
            tenantId: 'tenant-1',
            clientId: 'client-1',
            clientSecret: MicrosoftClientCredentialsTokenProviderTest::SECRET,
            tokenCache: null,
            authorityUrl: 'http://127.0.0.1:1',
            clock: $this->clock,
        );

        $this->expectException(MailerException::class);
        $this->expectExceptionMessageMatches('/^The request for the OAuth access token failed: /');

        $provider->getAccessToken();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidTokenResponses(): array
    {
        return [
            'no JSON' => ['<html>'],
            'JSON scalar' => ['"token"'],
            'no access_token' => ['{"token_type":"Bearer","expires_in":3600}'],
            'access_token no string' => ['{"access_token":1,"token_type":"Bearer","expires_in":3600}'],
            'empty access_token' => ['{"access_token":"","token_type":"Bearer","expires_in":3600}'],
            'no token_type' => ['{"access_token":"t","expires_in":3600}'],
            'token_type is not Bearer' => ['{"access_token":"t","token_type":"MAC","expires_in":3600}'],
            'no expires_in' => ['{"access_token":"t","token_type":"Bearer"}'],
            'expires_in is a float' => ['{"access_token":"t","token_type":"Bearer","expires_in":1.5}'],
            'expires_in is zero' => ['{"access_token":"t","token_type":"Bearer","expires_in":0}'],
            'expires_in is negative' => ['{"access_token":"t","token_type":"Bearer","expires_in":-5}'],
            'expires_in is text' => ['{"access_token":"t","token_type":"Bearer","expires_in":"soon"}'],
        ];
    }

    #[DataProvider('invalidTokenResponses')]
    public function testInvalidTokenResponseIsRejected(string $body): void
    {
        $this->server->respond(path: MicrosoftClientCredentialsTokenProviderTest::TOKEN_PATH, status: 200, body: $body);

        $this->expectException(MailerException::class);
        $this->expectExceptionMessageMatches('/^The response to the request for the OAuth access token /');

        $this->provider()->getAccessToken();
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function invalidArguments(): array
    {
        return [
            'empty tenant' => ['', 'client-1', 'secret'],
            'tenant with a slash' => ['a/b', 'client-1', 'secret'],
            'tenant with a query' => ['a?b', 'client-1', 'secret'],
            'empty client id' => ['tenant-1', '', 'secret'],
            'empty secret' => ['tenant-1', 'client-1', ''],
        ];
    }

    #[DataProvider('invalidArguments')]
    public function testInvalidArgumentsAreRejected(string $tenantId, string $clientId, string $clientSecret): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MicrosoftClientCredentialsTokenProvider(
            tenantId: $tenantId,
            clientId: $clientId,
            clientSecret: $clientSecret,
            tokenCache: null,
        );
    }

    private function provider(
        string $scope = MicrosoftClientCredentialsTokenProvider::GRAPH_SCOPE,
        ?FileCache $tokenCache = null,
    ): MicrosoftClientCredentialsTokenProvider {
        return new MicrosoftClientCredentialsTokenProvider(
            tenantId: 'tenant-1',
            clientId: 'client-1',
            clientSecret: MicrosoftClientCredentialsTokenProviderTest::SECRET,
            scope: $scope,
            authorityUrl: $this->server->url(''),
            clock: $this->clock,
            tokenCache: $tokenCache,
        );
    }

    private function respondWithToken(string $token, int $expiresIn): void
    {
        $this->server->respond(
            path: MicrosoftClientCredentialsTokenProviderTest::TOKEN_PATH,
            status: 200,
            body: '{"token_type":"Bearer","expires_in":' . $expiresIn . ',"access_token":"' . $token . '"}',
        );
    }
}
