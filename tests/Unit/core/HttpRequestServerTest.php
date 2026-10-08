<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\HttpRequest;
use actra\yuf\core\ProtocolEnum;
use actra\yuf\core\RequestMethodEnum;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The request line and the server data of a request built with the constructor (see HttpRequestFromGlobalsTest for
 * the values read from the superglobals).
 */
final class HttpRequestServerTest extends TestCase
{
    public function testDefaultsOfTheConstructor(): void
    {
        $httpRequest = new HttpRequest(host: 'www.example.com');

        $this->assertSame('www.example.com', $httpRequest->getHost());
        $this->assertSame(RequestMethodEnum::GET, $httpRequest->getMethod());
        $this->assertSame('/', $httpRequest->getUri());
        $this->assertSame('', $httpRequest->getQuery());
        $this->assertSame(ProtocolEnum::HTTPS, $httpRequest->getProtocol());
        $this->assertTrue($httpRequest->isSsl());
        $this->assertSame(0, $httpRequest->getPort());
        $this->assertSame('', $httpRequest->getServerName());
        $this->assertSame('', $httpRequest->getServerAddress());
        $this->assertSame('', $httpRequest->getRemoteAddress());
        $this->assertSame('', $httpRequest->getBody());
    }

    public function testEmptyHostIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The host of a request must not be empty.');

        new HttpRequest(host: '');
    }

    public function testHostKeepsThePortTheClientSent(): void
    {
        $httpRequest = HttpRequestFactory::create(host: 'www.example.com:8080');

        $this->assertSame('www.example.com:8080', $httpRequest->getHost());
    }

    public function testPortAndServerData(): void
    {
        $httpRequest = HttpRequestFactory::create(
            port: 8080,
            serverName: 'internal.example.com',
            serverAddress: '192.0.2.10',
        );

        $this->assertSame(8080, $httpRequest->getPort());
        $this->assertSame('internal.example.com', $httpRequest->getServerName());
        $this->assertSame('192.0.2.10', $httpRequest->getServerAddress());
    }

    public function testUriContainsPathAndQuery(): void
    {
        $httpRequest = HttpRequestFactory::create(uri: '/de/page?a=1&b=2', queryString: 'a=1&b=2');

        $this->assertSame('/de/page?a=1&b=2', $httpRequest->getUri());
        $this->assertSame('a=1&b=2', $httpRequest->getQuery());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pathProvider(): iterable
    {
        yield 'without query' => ['/de/page', '/de/page'];
        yield 'with query' => ['/de/page?a=1&b=2', '/de/page'];
        yield 'root' => ['/', '/'];
        yield 'root with query' => ['/?a=1', '/'];
        yield 'empty query' => ['/de/page?', '/de/page'];
        yield 'question mark inside query' => ['/de/page?a=what?&b=2', '/de/page'];
        yield 'encoded question mark in path' => ['/de/what%3Fpage?a=1', '/de/what%3Fpage'];
        yield 'only query' => ['?a=1', ''];
        yield 'umlaut before query' => ['/de/zürich?a=1', '/de/zürich'];
    }

    #[DataProvider('pathProvider')]
    public function testPathEndsBeforeTheFirstQuestionMark(string $uri, string $expectedPath): void
    {
        $this->assertSame($expectedPath, HttpRequestFactory::create(uri: $uri)->getPath());
    }

    public function testUrlUsesTheProtocolOfTheRequest(): void
    {
        $httpRequest = HttpRequestFactory::create(
            host: 'www.example.com',
            uri: '/de/page?a=1',
            protocol: ProtocolEnum::HTTP,
        );

        $this->assertSame('http://www.example.com/de/page?a=1', $httpRequest->getUrl());
    }

    public function testUrlWithExplicitProtocol(): void
    {
        $httpRequest = HttpRequestFactory::create(
            host: 'www.example.com',
            uri: '/de/page',
            protocol: ProtocolEnum::HTTP,
        );

        $this->assertSame('https://www.example.com/de/page', $httpRequest->getUrl(protocol: ProtocolEnum::HTTPS));
        $this->assertSame(ProtocolEnum::HTTP, $httpRequest->getProtocol());
        $this->assertFalse($httpRequest->isSsl());
    }

    /**
     * @return iterable<string, array{RequestMethodEnum, string}>
     */
    public static function requestMethodProvider(): iterable
    {
        foreach (RequestMethodEnum::cases() as $method) {
            yield $method->value => [$method, $method->value];
        }
    }

    #[DataProvider('requestMethodProvider')]
    public function testRequestMethod(RequestMethodEnum $method, string $expectedName): void
    {
        $this->assertSame($method, HttpRequestFactory::create(method: $method)->getMethod());
        $this->assertSame($expectedName, $method->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function addedRequestMethodProvider(): iterable
    {
        yield 'HEAD' => ['HEAD'];
        yield 'OPTIONS' => ['OPTIONS'];
    }

    #[DataProvider('addedRequestMethodProvider')]
    public function testRequestMethodEnumKnowsHeadAndOptions(string $method): void
    {
        $this->assertSame($method, RequestMethodEnum::from(value: $method)->name);
    }
}
