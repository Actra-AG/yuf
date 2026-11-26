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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The last step of the request pipeline of `Core::prepareHttpResponse()`: from the processed content to the response.
 */
final class ContentResponseFactoryTest extends TestCase
{
    private function createFactory(
        ?CspPolicySettings $cspPolicySettings,
        ?Language $language = null,
        bool $isPersonal = false,
    ): ContentResponseFactory {
        return new ContentResponseFactory(
            httpRequest: HttpRequestFactory::create(host: 'example.test'),
            cspPolicySettings: $cspPolicySettings,
            language: $language,
            isPersonal: $isPersonal,
        );
    }

    private function createHandlerWithETag(ContentType $contentType): ContentHandler
    {
        $contentHandler = new ContentHandler(contentType: $contentType, cspNonce: new CspNonce(value: 'fixed-nonce'));
        $contentHandler->setContent(contentString: 'content');
        $contentHandler->setETag(eTag: 'abc');

        return $contentHandler;
    }

    public function testPageWithETagMayBeStoredAndIsRevalidated(): void
    {
        $html = $this->createFactory(cspPolicySettings: new CspPolicySettings())
            ->create(contentHandler: $this->createHandlerWithETag(contentType: ContentType::createHtml()));
        $json = $this->createFactory(cspPolicySettings: null)
            ->create(contentHandler: $this->createHandlerWithETag(contentType: ContentType::createJson()));

        $this->assertSame('private, no-cache', $html->getHeader(key: 'Cache-Control'));
        $this->assertSame('"abc"', $html->getHeader(key: 'Etag'));
        $this->assertSame('private, no-cache', $json->getHeader(key: 'Cache-Control'));
        $this->assertSame('"abc"', $json->getHeader(key: 'Etag'));
    }

    public function testPersonalPageIsNeverStoredEvenWithETag(): void
    {
        $httpResponse = $this->createFactory(cspPolicySettings: null, isPersonal: true)
            ->create(contentHandler: $this->createHandlerWithETag(contentType: ContentType::createHtml()));

        $this->assertSame('private, no-store', $httpResponse->getHeader(key: 'Cache-Control'));
        $this->assertNull($httpResponse->getHeader(key: 'Etag'));
    }

    public function testErrorStatusIsNeverStoredEvenWithETag(): void
    {
        $contentHandler = $this->createHandlerWithETag(contentType: ContentType::createHtml());
        $contentHandler->httpStatusCode = HttpStatusCodeEnum::HTTP_CONFLICT;

        $httpResponse = $this->createFactory(cspPolicySettings: null)->create(contentHandler: $contentHandler);

        $this->assertSame('private, no-store', $httpResponse->getHeader(key: 'Cache-Control'));
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

    /**
     * @return array<string, array{ContentType}>
     */
    public static function contentTypeProvider(): array
    {
        return ['html' => [ContentType::createHtml()], 'json' => [ContentType::createJson()]];
    }

    #[DataProvider('contentTypeProvider')]
    public function testHeadersOfTheViewAreSentWithAnErrorStatus(ContentType $contentType): void
    {
        $contentHandler = new ContentHandler(contentType: $contentType, cspNonce: new CspNonce(value: 'fixed-nonce'));
        $contentHandler->setContent(contentString: 'slow down');
        $contentHandler->httpStatusCode = HttpStatusCodeEnum::HTTP_TOO_MANY_REQUESTS;
        $contentHandler->setRetryAfter(seconds: 30);

        $httpResponse = $this->createFactory(cspPolicySettings: new CspPolicySettings())
            ->create(contentHandler: $contentHandler);

        $this->assertSame(HttpStatusCodeEnum::HTTP_TOO_MANY_REQUESTS, $httpResponse->httpStatusCode);
        $this->assertSame('30', $httpResponse->getHeader(key: 'Retry-After'));
        $this->assertSame('private, no-store', $httpResponse->getHeader(key: 'Cache-Control'));
    }

    public function testHeadersOfTheViewDoNotChangeTheCaching(): void
    {
        $contentHandler = $this->createHandlerWithETag(contentType: ContentType::createJson());
        $contentHandler->setHeader(name: 'Link', value: '</a>; rel=next');

        $httpResponse = $this->createFactory(cspPolicySettings: null)->create(contentHandler: $contentHandler);

        $this->assertSame('</a>; rel=next', $httpResponse->getHeader(key: 'Link'));
        $this->assertSame('private, no-cache', $httpResponse->getHeader(key: 'Cache-Control'));
        $this->assertSame('"abc"', $httpResponse->getHeader(key: 'Etag'));
    }

    public function testRequestWithoutContentIsNotFound(): void
    {
        $contentHandler = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create());
        $contentHandler->setContent(contentString: " \n");

        $this->expectException(NotFoundException::class);
        $this->createFactory(cspPolicySettings: null)->create(contentHandler: $contentHandler);
    }
}
