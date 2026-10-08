<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\ContentHandler;
use actra\yuf\core\ContentResponseFactory;
use actra\yuf\core\ContentType;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\Language;
use actra\yuf\exception\NotFoundException;
use actra\yuf\security\CspNonce;
use actra\yuf\security\CspPolicySettings;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use PHPUnit\Framework\TestCase;

/**
 * The last step of the request pipeline of `Core::prepareHttpResponse()`: from the processed content to the response.
 */
final class ContentResponseFactoryTest extends TestCase
{
    private function createFactory(
        ?CspPolicySettings $cspPolicySettings,
        ?Language $language = null,
    ): ContentResponseFactory {
        return new ContentResponseFactory(
            httpRequest: HttpRequestFactory::create(host: 'example.test'),
            cspPolicySettings: $cspPolicySettings,
            language: $language,
        );
    }

    public function testHtmlContentGetsAContentSecurityPolicyWithTheNonce(): void
    {
        $contentHandler = new ContentHandler(
            contentType: ContentType::createHtml(),
            cspNonce: new CspNonce(value: 'fixed-nonce'),
        );
        $contentHandler->setContent(contentString: '<p>Hello</p>');

        $httpResponse = $this->createFactory(cspPolicySettings: new CspPolicySettings())
            ->create(contentHandler: $contentHandler);

        $this->assertSame('text/html; charset=utf-8', $httpResponse->getHeader(key: 'Content-Type'));
        $this->assertSame(
            1,
            preg_match(
                pattern: "/script-src [^;]*'nonce-fixed-nonce'/",
                subject: (string) $httpResponse->getHeader(key: 'Content-Security-Policy'),
            ),
        );
        $this->assertSame(HttpStatusCodeEnum::HTTP_OK, $httpResponse->httpStatusCode);
    }

    public function testSuppressedCspHeaderIsNotSent(): void
    {
        $contentHandler = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create());
        $contentHandler->setContent(contentString: '<p>Hello</p>');
        $contentHandler->suppressCspHeader();

        $httpResponse = $this->createFactory(cspPolicySettings: new CspPolicySettings())
            ->create(contentHandler: $contentHandler);

        $this->assertArrayNotHasKey('Content-Security-Policy', $httpResponse->listHeaders());
    }

    public function testWithoutPolicyNoCspHeaderIsSent(): void
    {
        $contentHandler = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create());
        $contentHandler->setContent(contentString: '<p>Hello</p>');

        $httpResponse = $this->createFactory(cspPolicySettings: null)->create(contentHandler: $contentHandler);

        $this->assertArrayNotHasKey('Content-Security-Policy', $httpResponse->listHeaders());
    }

    public function testStatusCodeOfTheContentHandlerIsKept(): void
    {
        $contentHandler = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create());
        $contentHandler->setContent(contentString: '<p>Missing</p>');
        $contentHandler->httpStatusCode = HttpStatusCodeEnum::HTTP_NOT_FOUND;

        $httpResponse = $this->createFactory(cspPolicySettings: null)->create(contentHandler: $contentHandler);

        $this->assertSame(HttpStatusCodeEnum::HTTP_NOT_FOUND, $httpResponse->httpStatusCode);
    }

    public function testJsonContentHasNoCspHeaderAndItsContentType(): void
    {
        $contentHandler = new ContentHandler(contentType: ContentType::createJson(), cspNonce: CspNonce::create());
        $contentHandler->setContent(contentString: '{"success":true}');
        $contentHandler->httpStatusCode = HttpStatusCodeEnum::HTTP_BAD_REQUEST;

        $httpResponse = $this->createFactory(cspPolicySettings: new CspPolicySettings())
            ->create(contentHandler: $contentHandler);

        $headers = $httpResponse->listHeaders();
        $this->assertSame('application/json; charset=utf-8', $httpResponse->getHeader(key: 'Content-Type'));
        $this->assertArrayNotHasKey('Content-Security-Policy', $headers);
        $this->assertSame(HttpStatusCodeEnum::HTTP_BAD_REQUEST, $httpResponse->httpStatusCode);
    }

    public function testContentTypeChangedByTheViewIsUsed(): void
    {
        $contentHandler = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create());
        $contentHandler->setContentType(contentType: ContentType::createXml());
        $contentHandler->setContent(contentString: '<a/>');

        $httpResponse = $this->createFactory(cspPolicySettings: new CspPolicySettings())
            ->create(contentHandler: $contentHandler);

        $this->assertSame('application/xml; charset=utf-8', $httpResponse->getHeader(key: 'Content-Type'));
    }

    public function testHtmlResponseHasTheContentLanguageOfTheRequest(): void
    {
        $contentHandler = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create());
        $contentHandler->setContent(contentString: '<p>Hello</p>');

        $httpResponse = $this->createFactory(
            cspPolicySettings: null,
            language: new Language(code: 'en', locale: 'en_US.UTF-8'),
        )->create(contentHandler: $contentHandler);

        $this->assertSame('en', $httpResponse->getHeader(key: 'Content-Language'));
    }

    public function testHtmlResponseWithoutLanguageHasNoContentLanguage(): void
    {
        $contentHandler = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create());
        $contentHandler->setContent(contentString: '<p>Hello</p>');

        $httpResponse = $this->createFactory(cspPolicySettings: null)->create(contentHandler: $contentHandler);

        $this->assertNull($httpResponse->getHeader(key: 'Content-Language'));
    }

    public function testRequestWithoutContentIsNotFound(): void
    {
        $contentHandler = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create());
        $contentHandler->setContent(contentString: " \n");

        $this->expectException(NotFoundException::class);
        $this->createFactory(cspPolicySettings: null)->create(contentHandler: $contentHandler);
    }
}
