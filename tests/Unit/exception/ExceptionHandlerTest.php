<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\exception;

use actra\yuf\Core;
use actra\yuf\core\ContentHandler;
use actra\yuf\core\ContentType;
use actra\yuf\core\RequestHandler;
use actra\yuf\exception\ExceptionHandler;
use actra\yuf\exception\ExceptionHandlerContext;
use actra\yuf\security\CspNonce;
use actra\yuf\security\CspPolicySettings;
use actra\yuf\security\SessionCsrfTokenSource;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\core\RecordingLogger;
use actra\yuf\tests\Double\exception\ContextExposingExceptionHandler;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * `register()` sets the global exception handler and keeps the registered instance statically; both are reset after
 * each test. Not covered: `handleException()` itself, because every path sends the response and exits.
 */
final class ExceptionHandlerTest extends TestCase
{
    private ReflectionProperty $registeredInstanceProperty;

    #[Override]
    protected function setUp(): void
    {
        $this->registeredInstanceProperty = new ReflectionProperty(
            class: ExceptionHandler::class,
            property: 'registeredInstance',
        );
        $this->registeredInstanceProperty->setValue(null, null);
    }

    #[Override]
    protected function tearDown(): void
    {
        if ($this->registeredInstanceProperty->getValue() !== null) {
            restore_exception_handler();
        }
        $this->registeredInstanceProperty->setValue(null, null);
    }

    private function createContext(): ExceptionHandlerContext
    {
        return new ExceptionHandlerContext(
            logger: new RecordingLogger(),
            cspNonce: CspNonce::create(),
            cspPolicySettings: new CspPolicySettings(),
            isDebug: true,
            core: new ReflectionClass(objectOrClass: Core::class)->newInstanceWithoutConstructor(),
            httpRequest: HttpRequestFactory::create(),
        );
    }

    public function testContextWithoutRegisterThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('ExceptionHandler is not registered: the context is not available.');

        new ContextExposingExceptionHandler()->context();
    }

    public function testRegisterKeepsTheContext(): void
    {
        $handler = new ContextExposingExceptionHandler();
        $context = $this->createContext();

        ExceptionHandler::register(individualExceptionHandler: $handler, context: $context);

        $this->assertSame($context, $handler->context());
    }

    public function testSecondRegisterThrows(): void
    {
        ExceptionHandler::register(individualExceptionHandler: null, context: $this->createContext());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('ExceptionHandler is already registered.');

        ExceptionHandler::register(individualExceptionHandler: null, context: $this->createContext());
    }

    public function testRegisterReturnsTheRegisteredHandler(): void
    {
        $handler = new ContextExposingExceptionHandler();

        $registered = ExceptionHandler::register(
            individualExceptionHandler: $handler,
            context: $this->createContext(),
        );

        $this->assertSame($handler, $registered);
    }

    public function testSetRequestHandlerTwiceThrows(): void
    {
        $handler = ExceptionHandler::register(individualExceptionHandler: null, context: $this->createContext());
        $requestHandler = new ReflectionClass(objectOrClass: RequestHandler::class)->newInstanceWithoutConstructor();
        $handler->setRequestHandler(requestHandler: $requestHandler);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The request handler is already set.');
        $handler->setRequestHandler(requestHandler: $requestHandler);
    }

    public function testSetContentHandlerTwiceThrows(): void
    {
        $handler = ExceptionHandler::register(individualExceptionHandler: null, context: $this->createContext());
        $contentHandler = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create());
        $handler->setContentHandler(contentHandler: $contentHandler);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The content handler is already set.');
        $handler->setContentHandler(contentHandler: $contentHandler);
    }

    public function testSetSessionTwiceThrows(): void
    {
        $handler = ExceptionHandler::register(individualExceptionHandler: null, context: $this->createContext());
        $handler->setSession(session: null, csrfTokenSource: null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The session is already set.');
        $handler->setSession(session: null, csrfTokenSource: null);
    }

    public function testSetSessionAcceptsASessionOnce(): void
    {
        $handler = ExceptionHandler::register(individualExceptionHandler: null, context: $this->createContext());
        $session = new Session(storage: new ArraySessionStorage());

        $handler->setSession(session: $session, csrfTokenSource: new SessionCsrfTokenSource(session: $session));

        $this->expectException(LogicException::class);
        $handler->setSession(session: $session, csrfTokenSource: null);
    }
}
