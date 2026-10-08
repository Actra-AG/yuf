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
}
