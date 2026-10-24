<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\spam\captcha;

use actra\yuf\form\spam\captcha\CaptchaResult;
use actra\yuf\form\spam\captcha\CaptchaStatusEnum;
use actra\yuf\form\spam\captcha\TurnstileCaptcha;
use actra\yuf\html\HtmlTag;
use actra\yuf\security\CspNonce;
use actra\yuf\tests\Double\api\ScriptedHttpServer;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Turnstile against a local server that plays the Siteverify API (no network).
 */
final class TurnstileCaptchaTest extends TestCase
{
    private const string SECRET = 'secret-key-value';
    private const string TOKEN = 'response-token-value';
    private const string PATH = '/turnstile/v0/siteverify';

    private ScriptedHttpServer $server;

    #[Override]
    protected function setUp(): void
    {
        $this->server = new ScriptedHttpServer();
    }

    /**
     * @param ?list<string> $hostnames
     */
    private function captcha(
        ?array $hostnames = ['www.example.com'],
        ?string $action = null,
        ?string $siteverifyUrl = null,
    ): TurnstileCaptcha {
        return new TurnstileCaptcha(
            siteKey: 'site-key',
            secretKey: TurnstileCaptchaTest::SECRET,
            hostnames: $hostnames,
            action: $action,
            siteverifyUrl: $siteverifyUrl ?? $this->server->url(TurnstileCaptchaTest::PATH),
        );
    }

    private function respond(string $body, int $status = 200): void
    {
        $this->server->respond(path: TurnstileCaptchaTest::PATH, status: $status, body: $body);
    }

    private static function assertWithoutSecrets(CaptchaResult $result): void
    {
        self::assertStringNotContainsString(TurnstileCaptchaTest::SECRET, $result->reason);
        self::assertStringNotContainsString(TurnstileCaptchaTest::TOKEN, $result->reason);
    }

    public function testValidResponseIsVerifiedWithSiteverify(): void
    {
        $this->respond(body: '{"success":true,"hostname":"www.example.com","error-codes":[],"action":""}');

        $result = $this->captcha()->verify(response: TurnstileCaptchaTest::TOKEN, remoteIp: '192.0.2.1');

        $this->assertSame(CaptchaStatusEnum::VALID, $result->status);
        $requests = $this->server->requests();
        $this->assertCount(1, $requests);
        $this->assertSame('POST', $requests[0]->method);
        $this->assertSame(TurnstileCaptchaTest::PATH, $requests[0]->uri);
        $this->assertSame('application/x-www-form-urlencoded; charset=utf-8', $requests[0]->header('Content-Type'));
        $this->assertSame(
            'secret=' . TurnstileCaptchaTest::SECRET . '&response=' . TurnstileCaptchaTest::TOKEN
                . '&remoteip=192.0.2.1',
            $requests[0]->body,
        );
    }

    public function testUnknownIpAddressIsNotSent(): void
    {
        $this->respond(body: '{"success":true,"hostname":"www.example.com"}');

        $this->captcha()->verify(response: TurnstileCaptchaTest::TOKEN, remoteIp: null);

        $this->assertStringNotContainsString('remoteip', $this->server->request(index: 0)->body);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidAnswerProvider(): iterable
    {
        return [
            'invalid response' => ['{"success":false,"error-codes":["invalid-input-response"]}'],
            'duplicate' => ['{"success":false,"error-codes":["timeout-or-duplicate"]}'],
            'no error codes' => ['{"success":false}'],
            'success not true' => ['{"success":"true","hostname":"www.example.com"}'],
            'other host name' => ['{"success":true,"hostname":"evil.example.net"}'],
            'no host name' => ['{"success":true}'],
        ];
    }

    #[DataProvider('invalidAnswerProvider')]
    public function testRejectedResponseIsInvalid(string $body): void
    {
        $this->respond(body: $body);

        $result = $this->captcha()->verify(response: TurnstileCaptchaTest::TOKEN, remoteIp: null);

        $this->assertSame(CaptchaStatusEnum::INVALID, $result->status);
        TurnstileCaptchaTest::assertWithoutSecrets(result: $result);
    }

    public function testErrorCodesAreTheReason(): void
    {
        $this->respond(body: '{"success":false,"error-codes":["invalid-input-response","<script>",1]}');

        $result = $this->captcha()->verify(response: TurnstileCaptchaTest::TOKEN, remoteIp: null);

        $this->assertSame('Turnstile rejected the response: invalid-input-response', $result->reason);
    }

    public function testHostNameIsComparedCaseInsensitively(): void
    {
        $this->respond(body: '{"success":true,"hostname":"WWW.Example.com"}');

        $result = $this->captcha(hostnames: ['example.com', 'www.EXAMPLE.com'])->verify(
            response: TurnstileCaptchaTest::TOKEN,
            remoteIp: null,
        );

        $this->assertSame(CaptchaStatusEnum::VALID, $result->status);
    }

    public function testHostNameCheckCanBeSkipped(): void
    {
        $this->respond(body: '{"success":true,"hostname":"example.com"}');

        $result = $this->captcha(hostnames: null)->verify(response: TurnstileCaptchaTest::TOKEN, remoteIp: null);

        $this->assertSame(CaptchaStatusEnum::VALID, $result->status);
    }

    public function testActionIsChecked(): void
    {
        $this->respond(body: '{"success":true,"hostname":"www.example.com","action":"contact"}');

        $this->assertSame(
            CaptchaStatusEnum::VALID,
            $this->captcha(action: 'contact')->verify(response: TurnstileCaptchaTest::TOKEN, remoteIp: null)->status,
        );
        $this->assertSame(
            CaptchaStatusEnum::INVALID,
            $this->captcha(action: 'login')->verify(response: TurnstileCaptchaTest::TOKEN, remoteIp: null)->status,
        );
    }

    /**
     * @return iterable<string, array{0: int, 1: string}>
     */
    public static function unavailableAnswerProvider(): iterable
    {
        return [
            'invalid secret' => [200, '{"success":false,"error-codes":["invalid-input-secret"]}'],
            'missing secret' => [200, '{"success":false,"error-codes":["missing-input-secret"]}'],
            'bad request' => [200, '{"success":false,"error-codes":["bad-request"]}'],
            'internal error' => [200, '{"success":false,"error-codes":["internal-error"]}'],
            'server error' => [500, '{"success":false}'],
            'no JSON' => [200, 'maintenance'],
            'no success' => [200, '{"hostname":"www.example.com"}'],
            'JSON list' => [200, '[true]'],
        ];
    }

    #[DataProvider('unavailableAnswerProvider')]
    public function testFailureOfTheProviderIsUnavailable(int $status, string $body): void
    {
        $this->respond(body: $body, status: $status);

        $result = $this->captcha()->verify(response: TurnstileCaptchaTest::TOKEN, remoteIp: null);

        $this->assertSame(CaptchaStatusEnum::UNAVAILABLE, $result->status);
        TurnstileCaptchaTest::assertWithoutSecrets(result: $result);
    }

    public function testUnreachableProviderIsUnavailable(): void
    {
        // Port 1 on the loopback address: the connection is refused at once
        $result = $this->captcha(siteverifyUrl: 'http://127.0.0.1:1/siteverify')->verify(
            response: TurnstileCaptchaTest::TOKEN,
            remoteIp: null,
        );

        $this->assertSame(CaptchaStatusEnum::UNAVAILABLE, $result->status);
        TurnstileCaptchaTest::assertWithoutSecrets(result: $result);
    }

    public function testTooLongResponseIsInvalidWithoutRequest(): void
    {
        $result = $this->captcha()->verify(response: str_repeat(string: 'a', times: 2049), remoteIp: null);

        $this->assertSame(CaptchaStatusEnum::INVALID, $result->status);
        $this->assertSame([], $this->server->requests());
    }

    public function testWidgetWithNonceAndAction(): void
    {
        $tags = $this->captcha(action: 'contact')->createWidgetTags(
            responseFieldName: 'captcha',
            cspNonce: new CspNonce(value: 'abc+/='),
        );

        $this->assertSame(
            '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" nonce="abc+/=" async defer></script>'
                . '<div class="cf-turnstile" data-sitekey="site-key" data-response-field-name="captcha"'
                . ' data-action="contact"></div>',
            implode(separator: '', array: array_map(
                callback: static fn(HtmlTag $tag): string => $tag->render(),
                array: $tags,
            )),
        );
    }

    public function testWidgetWithoutNonceAndAction(): void
    {
        $tags = $this->captcha()->createWidgetTags(responseFieldName: 'captcha', cspNonce: null);

        $this->assertSame(
            [
                '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>',
                '<div class="cf-turnstile" data-sitekey="site-key" data-response-field-name="captcha"></div>',
            ],
            array_map(callback: static fn(HtmlTag $tag): string => $tag->render(), array: $tags),
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: ?list<string>, 3: ?string}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        return [
            'empty site key' => ['', 'secret', null, null],
            'empty secret key' => ['site', '', null, null],
            'no host names' => ['site', 'secret', [], null],
            'action too long' => ['site', 'secret', null, str_repeat(string: 'a', times: 33)],
            'action with space' => ['site', 'secret', null, 'contact form'],
        ];
    }

    /**
     * @param ?list<string> $hostnames
     */
    #[DataProvider('invalidConfigurationProvider')]
    public function testInvalidConfigurationIsRejected(
        string $siteKey,
        string $secretKey,
        ?array $hostnames,
        ?string $action,
    ): void {
        $this->expectException(InvalidArgumentException::class);

        new TurnstileCaptcha(siteKey: $siteKey, secretKey: $secretKey, hostnames: $hostnames, action: $action);
    }
}
