<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\RequestLogFormatter;
use actra\yuf\core\RequestMethodEnum;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestLogFormatterTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function secretNameProvider(): iterable
    {
        yield 'password' => ['password'];
        yield 'upper case' => ['PASSWORD'];
        yield 'part of a name' => ['newPasswordRepeat'];
        yield 'token' => ['csrftoken'];
        yield 'secret' => ['client_secret'];
        yield 'csrf' => ['_csrf'];
        yield 'key' => ['apiKey'];
        yield 'auth' => ['Authorization'];
    }

    #[DataProvider('secretNameProvider')]
    public function testValueOfASecretNameIsMasked(string $name): void
    {
        $masked = new RequestLogFormatter()->maskSecrets(values: [$name => 'the-value', 'other' => 'visible']);

        $this->assertSame([$name => '***', 'other' => 'visible'], $masked);
    }

    public function testNestedSecretsAreMasked(): void
    {
        $masked = new RequestLogFormatter()->maskSecrets(values: [
            'user' => ['name' => 'Ada', 'password' => 'x', 'tokens' => ['a', 'b']],
            'list' => [['key' => 'k', 'id' => 5]],
        ]);

        $this->assertSame(
            [
                'user' => ['name' => 'Ada', 'password' => '***', 'tokens' => '***'],
                'list' => [['key' => '***', 'id' => 5]],
            ],
            $masked,
        );
    }

    public function testIntegerKeysAreNotMasked(): void
    {
        $this->assertSame([0 => 'a', 1 => 'b'], new RequestLogFormatter()->maskSecrets(values: ['a', 'b']));
    }

    public function testRequestDescribesTheClient(): void
    {
        $httpRequest = HttpRequestFactory::create(
            host: 'www.example.com',
            method: RequestMethodEnum::POST,
            uri: '/login.html?token=abc',
            remoteAddress: '192.0.2.77',
            headers: [
                'User-Agent' => 'TestBrowser/1.0',
                'Referer' => 'https://example.com/from?session=secret#top',
            ],
        );

        $log = new RequestLogFormatter()->format(httpRequest: $httpRequest);

        $this->assertStringContainsString('Request: POST /login.html' . PHP_EOL, $log);
        $this->assertStringContainsString('Host: www.example.com' . PHP_EOL, $log);
        $this->assertStringContainsString('IP address: 192.0.2.77' . PHP_EOL, $log);
        $this->assertStringContainsString('User agent: TestBrowser/1.0' . PHP_EOL, $log);
        $this->assertStringContainsString('Referrer: https://example.com/from' . PHP_EOL, $log);
        $this->assertStringNotContainsString('session=secret', $log);
        $this->assertStringNotContainsString('token=abc', $log);
    }

    public function testOnlyServerVariablesOfTheAllowListAreLogged(): void
    {
        $httpRequest = HttpRequestFactory::create(serverVariables: [
            'REQUEST_METHOD' => 'GET',
            'SERVER_PORT' => '443',
            'REQUEST_URI' => '/x?token=abc',
            'QUERY_STRING' => 'token=abc',
            'HTTP_COOKIE' => 'session=abc',
            'HTTP_AUTHORIZATION' => 'Bearer abc',
            'DB_PASSWORD' => 'abc',
            'HTTP_REFERER' => 'https://example.com/?token=abc',
        ]);

        $log = new RequestLogFormatter()->format(httpRequest: $httpRequest);

        $this->assertStringContainsString('REQUEST_METHOD = GET' . PHP_EOL, $log);
        $this->assertStringContainsString('SERVER_PORT = 443' . PHP_EOL, $log);
        $this->assertStringNotContainsString('REQUEST_URI', $log);
        $this->assertStringNotContainsString('QUERY_STRING', $log);
        $this->assertStringNotContainsString('HTTP_COOKIE', $log);
        $this->assertStringNotContainsString('HTTP_AUTHORIZATION', $log);
        $this->assertStringNotContainsString('DB_PASSWORD', $log);
        $this->assertStringNotContainsString('HTTP_REFERER', $log);
        $this->assertStringNotContainsString('abc', $log);
    }

    public function testParametersAreLoggedWithMaskedSecrets(): void
    {
        $httpRequest = HttpRequestFactory::create(
            queryParameters: ['page' => '2', 'access_token' => 'query-secret'],
            postParameters: [
                'email' => 'user@example.com',
                'password' => 'post-secret',
                'nested' => ['authCode' => 'x'],
            ],
        );

        $log = new RequestLogFormatter()->format(httpRequest: $httpRequest);

        $this->assertStringContainsString('[page] => 2', $log);
        $this->assertStringContainsString('[access_token] => ***', $log);
        $this->assertStringContainsString('[email] => user@example.com', $log);
        $this->assertStringContainsString('[password] => ***', $log);
        $this->assertStringContainsString('[authCode] => ***', $log);
        $this->assertStringNotContainsString('query-secret', $log);
        $this->assertStringNotContainsString('post-secret', $log);
    }

    public function testCookiesAreLoggedByNameOnly(): void
    {
        $httpRequest = HttpRequestFactory::create(cookies: ['PHPSESSID' => 'session-id', 'theme' => 'dark']);

        $log = new RequestLogFormatter()->format(httpRequest: $httpRequest);

        $this->assertStringContainsString('Cookie names = PHPSESSID, theme', $log);
        $this->assertStringNotContainsString('session-id', $log);
        $this->assertStringNotContainsString('dark', $log);
    }

    public function testUploadedFilesAreLoggedWithoutTheTemporaryPath(): void
    {
        $httpRequest = HttpRequestFactory::create(uploadedFiles: [
            'attachment' => ['name' => 'a.pdf', 'size' => 12, 'tmp_name' => '/tmp/php1234', 'error' => 0],
        ]);

        $log = new RequestLogFormatter()->format(httpRequest: $httpRequest);

        $this->assertStringContainsString('[name] => a.pdf', $log);
        $this->assertStringContainsString('[size] => 12', $log);
        $this->assertStringNotContainsString('/tmp/php1234', $log);
    }

    public function testControlCharactersCannotBreakTheLines(): void
    {
        $httpRequest = HttpRequestFactory::create(headers: ['User-Agent' => "Agent\r\nInjected: line"]);

        $log = new RequestLogFormatter()->format(httpRequest: $httpRequest);

        $this->assertStringContainsString('User agent: Agent??Injected: line' . PHP_EOL, $log);
    }
}
