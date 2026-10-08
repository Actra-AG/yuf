<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\ContentHandler;
use actra\yuf\core\ContentType;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\security\CspNonce;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Not covered: getHtmlDocument() after processRequest() and processRequest() itself (they need a resolved request).
 */
final class ContentHandlerTest extends TestCase
{
    public function testConstructorSetsContentType(): void
    {
        $contentType = ContentType::createJson();

        $handler = new ContentHandler(contentType: $contentType, cspNonce: CspNonce::create());

        $this->assertSame($contentType, $handler->getContentType());
        $this->assertSame(HttpStatusCodeEnum::HTTP_OK, $handler->httpStatusCode);
        $this->assertFalse($handler->hasContent());
        $this->assertSame('', $handler->getContent());
        $this->assertFalse($handler->suppressCspHeader);
    }

    public function testConstructorKeepsTheCspNonce(): void
    {
        $cspNonce = new CspNonce(value: 'fixed-nonce');

        $handler = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: $cspNonce);

        $this->assertSame($cspNonce, $handler->cspNonce);
    }

    public function testSetContent(): void
    {
        $handler = new ContentHandler(contentType: ContentType::createTxt(), cspNonce: CspNonce::create());

        $handler->setContent(contentString: 'abc');

        $this->assertTrue($handler->hasContent());
        $this->assertSame('abc', $handler->getContent());
    }

    public function testWhitespaceOnlyIsNoContent(): void
    {
        $handler = new ContentHandler(contentType: ContentType::createTxt(), cspNonce: CspNonce::create());
        $handler->setContent(contentString: " \n");

        $this->assertFalse($handler->hasContent());
        $handler->setContent(contentString: 'x');
        $this->assertSame('x', $handler->getContent());
    }

    public function testSetContentTwiceThrows(): void
    {
        $handler = new ContentHandler(contentType: ContentType::createTxt(), cspNonce: CspNonce::create());
        $handler->setContent(contentString: 'a');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('Content is already set. You are not allowed to overwrite it.');
        $handler->setContent(contentString: 'b');
    }

    public function testSetContentType(): void
    {
        $handler = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create());

        $handler->setContentType(contentType: ContentType::createXml());

        $this->assertTrue($handler->getContentType()->type === ContentType::XML);
    }

    public function testSetContentTypeWithUnknownCharsetThrows(): void
    {
        $handler = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs(
            'The content type "zzz" has no charset and cannot be set as content type of the response; use a content'
            . ' type with a charset, e.g. ContentType::createJson().',
        );
        $handler->setContentType(contentType: ContentType::createFromFileExtension(extension: 'zzz'));
    }

    public function testSuppressCspHeader(): void
    {
        $handler = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create());

        $handler->suppressCspHeader();

        $this->assertTrue($handler->suppressCspHeader);
    }

    public function testInstancesAreIndependent(): void
    {
        $first = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create());
        $second = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create());
        $first->setContent(contentString: 'a');

        $this->assertFalse($second->hasContent());
    }

    public function testHtmlDocumentBeforeProcessRequestThrows(): void
    {
        $handler = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The HTML document is only available while the request is processed.');
        $handler->getHtmlDocument();
    }
}
