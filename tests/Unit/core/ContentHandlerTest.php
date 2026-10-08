<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\BaseView;
use actra\yuf\core\ContentHandler;
use actra\yuf\core\ContentType;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LocaleHandler;
use actra\yuf\core\RequestHandler;
use actra\yuf\core\ResolvedRoute;
use actra\yuf\core\ResponseSender;
use actra\yuf\core\Route;
use actra\yuf\core\RouteCollection;
use actra\yuf\core\ViewContext;
use actra\yuf\core\ViewFactory;
use actra\yuf\core\ViewMap;
use actra\yuf\exception\NotFoundException;
use actra\yuf\form\FormContext;
use actra\yuf\security\CspNonce;
use actra\yuf\session\AbstractSessionHandler;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\tests\Double\core\CoreWorkDirectory;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\core\RecordingResponseSender;
use actra\yuf\tests\Double\core\TestView;
use actra\yuf\tests\Double\form\FormContextFactory;
use actra\yuf\tests\Double\session\NonStartingSessionHandler;
use actra\yuf\tests\Double\template\TemplateEngineFactory;
use Closure;
use InvalidArgumentException;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * `processRequest()` runs with a resolved request handler, a temporary view directory and the doubles of the tests.
 */
final class ContentHandlerTest extends TestCase
{
    private CoreWorkDirectory $workDirectory;

    #[Override]
    protected function setUp(): void
    {
        $this->workDirectory = new CoreWorkDirectory();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->workDirectory->cleanUp();
    }

    /**
     * @param ?Closure(): string $viewCallback
     */
    private function createRoute(?Closure $viewCallback = null, ?ViewFactory $viewFactory = null): Route
    {
        return new Route(
            path: '/',
            viewDirectory: $this->workDirectory->viewDirectory,
            viewCallback: $viewCallback,
            viewClassPrefix: 'actra\\yuf\\tests\\Double',
            viewGroup: 'frontend',
            defaultFileName: 'sample.html',
            defaultContentType: ContentType::createHtml(),
            viewFactory: $viewFactory,
        );
    }

    private function createResolvedRoute(Route $route, HttpRequest $httpRequest): ResolvedRoute
    {
        $requestHandler = new RequestHandler(
            httpRequest: $httpRequest,
            routeCollection: new RouteCollection(routes: [$route]),
            availableLanguages: new LanguageCollection(),
            allowedDomains: ['example.com'],
            session: null,
            responseSender: new RecordingResponseSender(),
        );
        return $requestHandler->resolveRoute();
    }

    private function processRequest(
        ContentHandler $handler,
        Route $route,
        ?HttpRequest $httpRequest = null,
        ?Session $session = null,
        ?AbstractSessionHandler $sessionHandler = null,
        ?FormContext $formContext = null,
        ?ResponseSender $responseSender = null,
    ): void {
        $httpRequest ??= HttpRequestFactory::create();
        $handler->processRequest(
            resolvedRoute: $this->createResolvedRoute(route: $route, httpRequest: $httpRequest),
            localeHandler: new LocaleHandler(language: null, availableLanguages: new LanguageCollection()),
            templateEngine: TemplateEngineFactory::create(
                cacheDirectory: $this->workDirectory->appDirectory . 'cache/',
                templateBaseDirectory: $this->workDirectory->baseDirectory,
            ),
            httpRequest: $httpRequest,
            session: $session,
            sessionHandler: $sessionHandler,
            formContext: $formContext ?? FormContextFactory::create(httpRequest: $httpRequest),
            copyright: '2020-2026',
            robots: 'noindex',
            responseSender: $responseSender ?? new RecordingResponseSender(),
        );
    }

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

    public function testProcessRequestSetsTheContentOfTheViewCallback(): void
    {
        $handler = new ContentHandler(contentType: ContentType::createTxt(), cspNonce: CspNonce::create());

        $this->processRequest(
            handler: $handler,
            route: $this->createRoute(viewCallback: fn(): string => 'from callback'),
        );

        $this->assertSame('from callback', $handler->getContent());
    }

    public function testProcessRequestRendersTheHtmlDocumentOfAView(): void
    {
        $handler = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create());

        $this->processRequest(handler: $handler, route: $this->createRoute());

        $this->assertSame('<p>Hello</p>', $handler->getContent());
    }

    public function testProcessRequestPassesItsArgumentsToTheViewContext(): void
    {
        $httpRequest = HttpRequestFactory::create();
        $formContext = FormContextFactory::create(httpRequest: $httpRequest);
        $session = new Session(storage: new ArraySessionStorage());
        $sessionHandler = new NonStartingSessionHandler();
        $responseSender = new RecordingResponseSender();
        $viewContext = null;
        $route = $this->createRoute(
            viewFactory: new ViewMap()->add(
                fileTitle: 'sample',
                create: function (ViewContext $context) use (&$viewContext): BaseView {
                    $viewContext = $context;

                    return new TestView(context: $context);
                },
            ),
        );
        $handler = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create());

        $this->processRequest(
            handler: $handler,
            route: $route,
            httpRequest: $httpRequest,
            session: $session,
            sessionHandler: $sessionHandler,
            formContext: $formContext,
            responseSender: $responseSender,
        );

        $this->assertInstanceOf(ViewContext::class, $viewContext);
        $this->assertSame($httpRequest, $viewContext->httpRequest);
        $this->assertSame($session, $viewContext->session);
        $this->assertSame($sessionHandler, $viewContext->sessionHandler);
        $this->assertNotNull($viewContext->authSession);
        $this->assertSame($formContext, $viewContext->formContext);
        $this->assertSame($responseSender, $viewContext->responseSender);
        $this->assertSame($handler, $viewContext->content);
    }

    public function testProcessRequestWithoutSessionHasNoAuthSession(): void
    {
        $viewContext = null;
        $route = $this->createRoute(
            viewFactory: new ViewMap()->add(
                fileTitle: 'sample',
                create: function (ViewContext $context) use (&$viewContext): BaseView {
                    $viewContext = $context;

                    return new TestView(context: $context);
                },
            ),
        );

        $this->processRequest(
            handler: new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create()),
            route: $route,
        );

        $this->assertInstanceOf(ViewContext::class, $viewContext);
        $this->assertNull($viewContext->session);
        $this->assertNull($viewContext->sessionHandler);
        $this->assertNull($viewContext->authSession);
    }

    public function testHtmlDocumentUsesTheCopyrightAndTheRobotsOfProcessRequest(): void
    {
        file_put_contents(
            filename: $this->workDirectory->viewDirectory . 'frontend/html/sample.html',
            data: "<p>{tst:text value='copyright'}|{tst:text value='robots'}|{tst:text value='language'}</p>",
        );
        $handler = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create());

        $this->processRequest(handler: $handler, route: $this->createRoute());

        $this->assertSame('<p>2020-2026|noindex|</p>', $handler->getContent());
        $this->assertSame($handler->getHtmlDocument(), $handler->getHtmlDocument());
    }

    public function testProcessRequestTwiceThrows(): void
    {
        $handler = new ContentHandler(contentType: ContentType::createTxt(), cspNonce: CspNonce::create());
        $route = $this->createRoute(viewCallback: fn(): string => 'x');
        $this->processRequest(handler: $handler, route: $route);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The request is already processed.');

        $this->processRequest(handler: $handler, route: $route);
    }

    public function testProcessRequestOfAMissingContentFileThrowsNotFound(): void
    {
        unlink(filename: $this->workDirectory->viewDirectory . 'frontend/html/sample.html');
        $handler = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create());
        $outputBufferLevel = ob_get_level();

        try {
            $this->processRequest(handler: $handler, route: $this->createRoute());
            ContentHandlerTest::fail('A missing content file must throw a NotFoundException.');
        } catch (NotFoundException) {
            // The output buffer of the failed view is discarded
            $this->assertSame($outputBufferLevel, ob_get_level());
            $this->assertFalse($handler->hasContent());
        }
    }
}
