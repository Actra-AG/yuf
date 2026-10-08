<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\tests\Double\core\HttpRequestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The client data of a request built with the constructor: remote address, user agent, referrer, browser languages.
 */
final class HttpRequestClientTest extends TestCase
{
    public function testRemoteAddress(): void
    {
        $this->assertSame('192.0.2.1', HttpRequestFactory::create(remoteAddress: '192.0.2.1')->getRemoteAddress());
    }

    public function testMissingRemoteAddressIsEmptyString(): void
    {
        $this->assertSame('', HttpRequestFactory::create(remoteAddress: '')->getRemoteAddress());
    }

    public function testUserAgentIsTheHeader(): void
    {
        $httpRequest = HttpRequestFactory::create(headers: ['User-Agent' => 'ExampleBrowser/1.0']);

        $this->assertSame('ExampleBrowser/1.0', $httpRequest->getUserAgent());
    }

    public function testMissingUserAgentIsEmptyString(): void
    {
        $this->assertSame('', HttpRequestFactory::create()->getUserAgent());
    }

    public function testReferrerIsTheRefererHeader(): void
    {
        $httpRequest = HttpRequestFactory::create(headers: ['Referer' => 'https://www.example.com/previous?a=1']);

        $this->assertSame('https://www.example.com/previous?a=1', $httpRequest->getReferrer());
    }

    public function testMissingReferrerIsEmptyString(): void
    {
        $this->assertSame('', HttpRequestFactory::create()->getReferrer());
    }

    /**
     * @return iterable<string, array{string|null, list<string>}>
     */
    public static function browserLanguagesProvider(): iterable
    {
        yield 'header missing' => [null, []];
        yield 'empty header' => ['', []];
        yield 'single language' => ['de', ['de']];
        yield 'regions are cut, duplicates are removed' => ['de-CH,de;q=0.9,en;q=0.8', ['de', 'en']];
        yield 'duplicate language with a lower quality' => ['de-CH;q=0.8,de-AT;q=0.9', ['de']];
        yield 'sorted by quality' => ['en;q=0.5,fr;q=0.9,de', ['de', 'fr', 'en']];
        yield 'equal qualities keep their order' => ['fr,de', ['fr', 'de']];
        yield 'equal qualities with q keep their order' => ['fr;q=0.8,de;q=0.8,en;q=0.7', ['fr', 'de', 'en']];
        yield 'explicit q=1 equals the default' => ['fr;q=1,de', ['fr', 'de']];
        yield 'three decimals are compared exactly' => ['de;q=0.333,en;q=0.334', ['en', 'de']];
        yield 'no float rounding error between 0.57 and 0.56' => ['de;q=0.56,en;q=0.57', ['en', 'de']];
        yield 'quality rounded to thousandths' => ['de;q=0.7,en;q=0.696', ['de', 'en']];
        yield 'whitespace around the language' => [' en-US , de;q=0.5', ['en', 'de']];
        yield 'spaces around the quality' => ['de, en ; q = 0.9 ,fr;q=0.5', ['de', 'en', 'fr']];
        yield 'quality parameter in capitals' => ['de;Q=0.5,en', ['en', 'de']];
        yield 'wildcard is skipped' => ['de,*;q=0.5', ['de']];
        yield 'only a wildcard' => ['*', []];
        yield 'quality zero is kept last' => ['de,en;q=0', ['de', 'en']];
        yield 'quality above one is clamped to one' => ['de,en;q=2', ['de', 'en']];
        yield 'non-numeric quality counts as zero' => ['de;q=abc,en;q=0.1', ['en', 'de']];
        yield 'empty entries are skipped' => [',de,,', ['de']];
        yield 'only a comma' => [',', []];
        yield 'other parameters are ignored' => ['de;level=1;q=0.5,en', ['en', 'de']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('browserLanguagesProvider')]
    public function testListBrowserLanguagesByQuality(?string $header, array $expected): void
    {
        $headers = $header === null ? [] : ['Accept-Language' => $header];

        $this->assertSame($expected, HttpRequestFactory::create(headers: $headers)->listBrowserLanguagesByQuality());
    }
}
