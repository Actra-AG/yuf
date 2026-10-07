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
use Exception;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Not covered: getHtmlDocument() (HtmlDocument reads RequestHandler::get() until step 10) and register() (runs the
 * whole request).
 */
final class ContentHandlerTest extends TestCase
{
    public function testConstructorSetsContentType(): void
    {
        $contentType = ContentType::createJson();

        $handler = new ContentHandler(contentType: $contentType);

        $this->assertSame($contentType, $handler->getContentType());
        $this->assertSame(HttpStatusCodeEnum::HTTP_OK, $handler->httpStatusCode);
        $this->assertFalse($handler->hasContent());
        $this->assertSame('', $handler->getContent());
        $this->assertFalse($handler->suppressCspHeader);
    }

    public function testSetContent(): void
    {
        $handler = new ContentHandler(contentType: ContentType::createTxt());

        $handler->setContent(contentString: 'abc');

        $this->assertTrue($handler->hasContent());
        $this->assertSame('abc', $handler->getContent());
    }

    public function testWhitespaceOnlyIsNoContent(): void
    {
        $handler = new ContentHandler(contentType: ContentType::createTxt());
        $handler->setContent(contentString: " \n");

        $this->assertFalse($handler->hasContent());
        $handler->setContent(contentString: 'x');
        $this->assertSame('x', $handler->getContent());
    }

    public function testSetContentTwiceThrows(): void
    {
        $handler = new ContentHandler(contentType: ContentType::createTxt());
        $handler->setContent(contentString: 'a');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('Content is already set. You are not allowed to overwrite it.');
        $handler->setContent(contentString: 'b');
    }

    public function testSetContentType(): void
    {
        $handler = new ContentHandler(contentType: ContentType::createHtml());

        $handler->setContentType(contentType: ContentType::createXml());

        $this->assertTrue($handler->getContentType()->type === ContentType::XML);
    }

    public function testSetContentTypeWithUnknownCharsetThrows(): void
    {
        $handler = new ContentHandler(contentType: ContentType::createHtml());

        $this->expectException(Exception::class);
        $handler->setContentType(contentType: ContentType::createFromFileExtension(extension: 'zzz'));
    }

    public function testSuppressCspHeader(): void
    {
        $handler = new ContentHandler(contentType: ContentType::createHtml());

        $handler->suppressCspHeader();

        $this->assertTrue($handler->suppressCspHeader);
    }

    public function testInstancesAreIndependent(): void
    {
        $first = new ContentHandler(contentType: ContentType::createHtml());
        $second = new ContentHandler(contentType: ContentType::createHtml());
        $first->setContent(contentString: 'a');

        $this->assertFalse($second->hasContent());
        $this->assertFalse(ContentHandler::isRegistered());
    }
}
