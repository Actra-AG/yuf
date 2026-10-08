<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\security;

use actra\yuf\core\ProtocolEnum;
use actra\yuf\security\CspPolicySettings;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CspPolicySettingsTest extends TestCase
{
    public function testPlaceholdersAreReplacedWithProtocolAndHostOfTheRequest(): void
    {
        $settings = new CspPolicySettings(
            defaultSrc: "'self' {PROTOCOL}://{HOST}",
            imgSrc: 'data: {PROTOCOL}://{HOST}',
        );
        $httpRequest = HttpRequestFactory::create(host: 'www.example.com:8443', protocol: ProtocolEnum::HTTPS);

        $policy = $settings->getHttpHeaderDataString(nonce: 'n', httpRequest: $httpRequest);

        $this->assertStringContainsString("default-src 'self' https://www.example.com:8443;", $policy);
        $this->assertStringContainsString('img-src data: https://www.example.com:8443;', $policy);
    }

    public function testHttpRequestUsesHttp(): void
    {
        $settings = new CspPolicySettings(defaultSrc: '{PROTOCOL}://{HOST}');
        $httpRequest = HttpRequestFactory::create(host: 'localhost', protocol: ProtocolEnum::HTTP);

        $policy = $settings->getHttpHeaderDataString(nonce: 'n', httpRequest: $httpRequest);

        $this->assertStringContainsString('default-src http://localhost;', $policy);
    }

    public function testNonceIsAddedToScriptsAndStyles(): void
    {
        $settings = new CspPolicySettings();

        $policy = $settings->getHttpHeaderDataString(nonce: 'abc', httpRequest: HttpRequestFactory::create());

        $this->assertStringContainsString("script-src 'strict-dynamic' 'nonce-abc'", $policy);
        $this->assertStringContainsString("style-src 'self' 'nonce-abc'", $policy);
    }

    public function testDefaultPolicy(): void
    {
        $policy = new CspPolicySettings()->getHttpHeaderDataString(
            nonce: 'abc',
            httpRequest: HttpRequestFactory::create(host: 'www.example.com', protocol: ProtocolEnum::HTTPS),
        );

        $this->assertSame(
            "default-src 'self' data: https://www.example.com; style-src 'self' 'nonce-abc'; font-src 'self'; "
            . "img-src 'self' data: https://www.example.com; object-src 'none'; "
            . "script-src 'strict-dynamic' 'nonce-abc'; connect-src 'none'; base-uri 'self'; frame-src 'none'; "
            . "frame-ancestors 'none';",
            $policy,
        );
    }

    public function testDefaultPolicyHasNoUnsafeKeywords(): void
    {
        $policy = new CspPolicySettings()->getHttpHeaderDataString(
            nonce: 'abc',
            httpRequest: HttpRequestFactory::create(),
        );

        $this->assertStringNotContainsString('unsafe-inline', $policy);
        $this->assertStringNotContainsString('unsafe-eval', $policy);
    }

    public function testNoNonceIsAddedWithoutNonce(): void
    {
        $policy = new CspPolicySettings()->getHttpHeaderDataString(
            nonce: '',
            httpRequest: HttpRequestFactory::create(),
        );

        $this->assertStringNotContainsString('nonce-', $policy);
    }

    public function testNoNonceIsAddedToNoneOrUnsafeInline(): void
    {
        $settings = new CspPolicySettings(styleSrc: "'none'", scriptSrc: "'self' 'unsafe-inline'");

        $policy = $settings->getHttpHeaderDataString(nonce: 'abc', httpRequest: HttpRequestFactory::create());

        $this->assertStringContainsString("style-src 'none';", $policy);
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline';", $policy);
    }

    public function testEmptyDirectivesAreLeftOut(): void
    {
        $settings = new CspPolicySettings(
            defaultSrc: '',
            styleSrc: '',
            fontSrc: '',
            imgSrc: '',
            objectSrc: '',
            scriptSrc: '',
            connectSrc: '',
            baseUri: '',
            frameSrc: '',
            frameAncestors: '',
            mediaSrc: 'https://media.example.com',
        );

        $policy = $settings->getHttpHeaderDataString(nonce: 'abc', httpRequest: HttpRequestFactory::create());

        $this->assertSame('media-src https://media.example.com;', $policy);
    }

    public function testPolicyWithoutDirectivesIsEmpty(): void
    {
        $settings = new CspPolicySettings(
            defaultSrc: '',
            styleSrc: '',
            fontSrc: '',
            imgSrc: '',
            objectSrc: '',
            scriptSrc: '',
            connectSrc: '',
            baseUri: '',
            frameSrc: '',
            frameAncestors: '',
        );

        $this->assertSame(
            '',
            $settings->getHttpHeaderDataString(nonce: 'abc', httpRequest: HttpRequestFactory::create()),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function maliciousHostProvider(): iterable
    {
        yield 'semicolon' => ['example.com; script-src *'];
        yield 'space' => ['example.com evil.example.org'];
        yield 'quote' => ["example.com'"];
        yield 'comma' => ['a.example.com,b.example.com'];
        yield 'empty after trim' => ['/'];
    }

    #[DataProvider('maliciousHostProvider')]
    public function testHostThatCouldAddDirectivesIsNotPutIntoThePolicy(string $host): void
    {
        $settings = new CspPolicySettings(defaultSrc: '{PROTOCOL}://{HOST}');

        $policy = $settings->getHttpHeaderDataString(
            nonce: 'n',
            httpRequest: HttpRequestFactory::create(host: $host),
        );

        $this->assertStringStartsWith('default-src https://invalid.invalid;', $policy);
        $this->assertStringNotContainsString('script-src *', $policy);
    }

    public function testIpv6HostWithPortIsKept(): void
    {
        $settings = new CspPolicySettings(defaultSrc: '{PROTOCOL}://{HOST}');

        $policy = $settings->getHttpHeaderDataString(
            nonce: 'n',
            httpRequest: HttpRequestFactory::create(host: '[2001:db8::1]:8443'),
        );

        $this->assertStringStartsWith('default-src https://[2001:db8::1]:8443;', $policy);
    }
}
