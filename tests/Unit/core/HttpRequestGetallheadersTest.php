<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\HttpRequest;
use actra\yuf\tests\Double\core\StaticRequestHeaders;
use Override;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * `HttpRequest::fromGlobals()` where `getallheaders()` exists (Apache, FPM). The function is a stand-in that is
 * defined in the process of each test.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class HttpRequestGetallheadersTest extends TestCase
{
    /** @var array<mixed> */
    private array $serverBackup = [];

    #[Override]
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../Fixture/core/getallheaders.php';
        $this->serverBackup = $_SERVER;
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_HOST' => 'www.example.com'];
    }

    #[Override]
    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        StaticRequestHeaders::$headers = [];
    }

    public function testHeadersThatTheServerVariablesLackAreAdded(): void
    {
        StaticRequestHeaders::$headers = ['Authorization' => 'Bearer fastcgi', 'X-Custom' => 'value'];

        $httpRequest = HttpRequest::fromGlobals();

        $this->assertSame('fastcgi', $httpRequest->getBearerToken());
        $this->assertSame('value', $httpRequest->getHeader(name: 'x-custom'));
    }

    public function testServerVariablesWinOverGetallheaders(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer from server';
        StaticRequestHeaders::$headers = ['Authorization' => 'Bearer from function'];

        $this->assertSame('from server', HttpRequest::fromGlobals()->getBearerToken());
    }

    public function testNoHeadersAtAll(): void
    {
        $httpRequest = HttpRequest::fromGlobals();

        $this->assertNull($httpRequest->getBearerToken());
        $this->assertNull($httpRequest->getHeader(name: 'Authorization'));
    }
}
