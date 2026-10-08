<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\HttpRequest;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of the static HttpRequest before the redesign (docs/http-request/plan.md, step 1).
 *
 * The browser languages are cached statically for the whole process, so every test runs in its own process.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class HttpRequestClientTest extends TestCase
{
    /** @var array<mixed> */
    private array $serverBackup = [];

    #[Override]
    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $_SERVER = [];
    }

    #[Override]
    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
    }

    public function testRemoteAddress(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.0.2.1';

        $this->assertSame('192.0.2.1', HttpRequest::getRemoteAddress());
    }

    public function testMissingRemoteAddressIsEmptyString(): void
    {
        $this->assertSame('', HttpRequest::getRemoteAddress());
    }

    public function testUserAgent(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'ExampleBrowser/1.0';

        $this->assertSame('ExampleBrowser/1.0', HttpRequest::getUserAgent());
    }

    public function testMissingUserAgentIsEmptyString(): void
    {
        $this->assertSame('', HttpRequest::getUserAgent());
    }

    public function testReferrer(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://www.example.com/previous?a=1';

        $this->assertSame('https://www.example.com/previous?a=1', HttpRequest::getReferrer());
    }

    public function testMissingReferrerIsEmptyString(): void
    {
        $this->assertSame('', HttpRequest::getReferrer());
    }

    /**
     * @return iterable<string, array{string|null, list<string>}>
     */
    public static function browserLanguagesProvider(): iterable
    {
        yield 'header missing' => [null, []];
        yield 'empty header' => ['', []];
        yield 'single language' => ['de', ['de']];
        yield 'regions are cut, duplicates are kept' => ['de-CH,de;q=0.9,en;q=0.8', ['de', 'de', 'en']];
        yield 'sorted by quality' => ['en;q=0.5,fr;q=0.9,de', ['de', 'fr', 'en']];
        yield 'equal qualities, first wins' => ['fr,de', ['fr']];
        yield 'equal qualities with q, first wins' => ['fr;q=0.8,de;q=0.8,en;q=0.7', ['fr', 'en']];
        yield 'explicit q=1 equals the default' => ['fr;q=1,de', ['fr']];
        yield 'quality with more decimals is rounded to two' => ['de;q=0.333,en;q=0.334', ['de']];
        yield 'quality rounded up' => ['de;q=0.7,en;q=0.696', ['de']];
        yield 'whitespace around the language' => [' en-US , de;q=0.5', ['en', 'de']];
        yield 'wildcard' => ['de,*;q=0.5', ['de', '*']];
        yield 'quality zero' => ['de,en;q=0', ['de', 'en']];
        yield 'quality above one sorts first' => ['de,en;q=2', ['en', 'de']];
        yield 'non-numeric quality counts as zero' => ['de,en;q=abc', ['de', 'en']];
        yield 'space after the semicolon is not recognized' => ['de, en; q=0.5', ['de']];
        yield 'empty first part wins with the default quality' => [',de,,', ['']];
        yield 'only a comma' => [',', ['']];
        yield 'float imprecision merges 0.57 and 0.56' => ['de;q=0.57,en;q=0.56', ['de']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('browserLanguagesProvider')]
    public function testListBrowserLanguagesByQuality(?string $header, array $expected): void
    {
        if ($header !== null) {
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $header;
        }

        $this->assertSame($expected, HttpRequest::listBrowserLanguagesByQuality());
    }

    public function testBrowserLanguagesAreCachedForTheWholeProcess(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de';
        $this->assertSame(['de'], HttpRequest::listBrowserLanguagesByQuality());

        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en';

        $this->assertSame(['de'], HttpRequest::listBrowserLanguagesByQuality());
    }

    public function testEmptyBrowserLanguagesAreCachedAfterTheFirstCall(): void
    {
        $this->assertSame([], HttpRequest::listBrowserLanguagesByQuality());

        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de';

        $this->assertSame([], HttpRequest::listBrowserLanguagesByQuality());
    }
}
