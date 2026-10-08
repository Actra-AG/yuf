<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit;

use actra\yuf\Core;
use actra\yuf\core\ContentType;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LocaleHandler;
use actra\yuf\core\ProtocolEnum;
use actra\yuf\core\Route;
use actra\yuf\core\RouteCollection;
use actra\yuf\exception\ExceptionHandler;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\security\CspPolicySettings;
use actra\yuf\template\TemplateData;
use actra\yuf\tests\Double\core\CoreWorkDirectory;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\core\RecordingLogger;
use actra\yuf\tests\Double\core\RecordingResponseSender;
use actra\yuf\tests\Double\session\NonStartingSessionHandler;
use actra\yuf\tests\Double\template\NamedTag;
use InvalidArgumentException;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * Builds `Core` with the constructor, from temporary directories and a request without globals. Not covered:
 * `Core::fromEnvironment()` (it registers the autoloader and the error handler, reads `$_SERVER` and can only be called
 * once per process); `prepareHttpResponse()` registers the global exception handler, which each test removes.
 */
final class CoreTest extends TestCase
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
        // The handler of a test that prepared a response must not stay for the next test
        if ($this->isExceptionHandlerRegistered()) {
            restore_exception_handler();
        }
        $this->workDirectory->cleanUp();
        unset($_SESSION); // Sessions are disabled in the CLI
    }

    private function isExceptionHandlerRegistered(): bool
    {
        $handler = set_exception_handler(callback: null);
        restore_exception_handler();

        return is_array(value: $handler) && $handler[0] instanceof ExceptionHandler;
    }

    private function createCore(
        ?HttpRequest $httpRequest = null,
        int $copyrightYear = 2020,
        bool $debug = false,
    ): Core {
        return new Core(
            settings: $this->workDirectory->createSettings(copyrightYear: $copyrightYear, debug: $debug),
            httpRequest: $httpRequest ?? HttpRequestFactory::create(),
            responseSender: new RecordingResponseSender(),
        );
    }

    private function createViewRoute(string $path = '/'): Route
    {
        return new Route(
            path: $path,
            viewDirectory: $this->workDirectory->viewDirectory,
            viewClassPrefix: 'actra\yuf\tests\Double',
            viewGroup: 'frontend',
            defaultFileName: 'sample.html',
            defaultContentType: ContentType::createHtml(),
        );
    }

    private function createTextRoute(string $content = 'Hello World!'): Route
    {
        return new Route(
            path: '/',
            viewDirectory: $this->workDirectory->viewDirectory,
            viewCallback: fn(): string => $content,
            defaultContentType: ContentType::createTxt(),
        );
    }

    public function testConstructorTakesTheSettingsAndTheRequest(): void
    {
        $settings = $this->workDirectory->createSettings(copyrightYear: 2019);
        $httpRequest = HttpRequestFactory::create();

        $core = new Core(settings: $settings, httpRequest: $httpRequest);

        $this->assertSame($httpRequest, $core->httpRequest);
        $this->assertSame($settings->environmentSettings, $core->environmentSettings);
        $this->assertSame('mail.example.com', $core->environmentSettings->getString(key: 'mailer.hostname'));
        $this->assertSame(2019, $core->copyrightYear);
        $this->assertSame($settings->documentRoot, $core->documentRoot);
        $this->assertSame($settings->frameworkDirectory, $core->frameworkDirectory);
        $this->assertSame($settings->baseDirectory, $core->baseDirectory);
        $this->assertSame($settings->appDirectory, $core->appDirectory);
        $this->assertSame($settings->cacheDirectory, $core->cacheDirectory);
        $this->assertSame($settings->errorDocsDirectory, $core->errorDocsDirectory);
        $this->assertSame($settings->logDirectory, $core->logDirectory);
        $this->assertSame($settings->settingsDirectory, $core->settingsDirectory);
        $this->assertSame($settings->snippetsDirectory, $core->snippetsDirectory);
        $this->assertSame($settings->viewDirectory, $core->viewDirectory);
        $this->assertSame(['example.com'], $core->allowedDomains);
        $this->assertFalse($core->debug);
        $this->assertSame('noindex', $core->robots);
        $this->assertTrue($core->availableLanguages->isEmpty());
        $this->assertNull($core->session);
        $this->assertNull($core->sessionHandler);
        $this->assertNull($core->cspPolicySettings);
        $this->assertSame($httpRequest, $core->formContext->httpRequest);
        $this->assertNull($core->formContext->csrfTokenSource);
    }

    public function testConstructorTouchesNoGlobals(): void
    {
        $this->createCore();

        $this->assertFalse($this->isExceptionHandlerRegistered());
    }

    public function testRenderCopyrightYear(): void
    {
        $currentYear = (int) date(format: 'Y');

        $this->assertSame('2020-' . $currentYear, $this->createCore(copyrightYear: 2020)->renderCopyrightYear());
        $this->assertSame((string) $currentYear, $this->createCore(copyrightYear: $currentYear)->renderCopyrightYear());
    }

    public function testPrepareHttpResponseRendersTheViewOfTheRoute(): void
    {
        $core = $this->createCore();

        $httpResponse = $core->prepareHttpResponse(
            logger: new RecordingLogger(),
            routeCollection: new RouteCollection(routes: [$this->createViewRoute()]),
            individualSessionHandler: false,
        );

        $this->assertSame(HttpStatusCodeEnum::HTTP_OK, $httpResponse->httpStatusCode);
        $this->assertSame('<p>Hello</p>', $httpResponse->getContentString());
        $this->assertSame('text/html; charset=utf-8', $httpResponse->getHeader(key: 'Content-Type'));
        $this->assertNotNull($httpResponse->getHeader(key: 'Content-Security-Policy'));
        $this->assertNull($core->session);
        $this->assertNull($core->sessionHandler);
        $this->assertNotNull($core->cspPolicySettings);
        $this->assertTrue($this->isExceptionHandlerRegistered());
    }

    public function testPrepareHttpResponseWithoutCspPolicy(): void
    {
        $httpResponse = $this->createCore()->prepareHttpResponse(
            logger: new RecordingLogger(),
            routeCollection: new RouteCollection(routes: [$this->createViewRoute()]),
            cspPolicySettings: null,
            individualSessionHandler: false,
        );

        $this->assertNull($httpResponse->getHeader(key: 'Content-Security-Policy'));
    }

    public function testPrepareHttpResponseWithRouteCallback(): void
    {
        $httpResponse = $this->createCore()->prepareHttpResponse(
            logger: new RecordingLogger(),
            routeCollection: new RouteCollection(routes: [$this->createTextRoute(content: 'Hi there')]),
            cspPolicySettings: new CspPolicySettings(),
            individualSessionHandler: false,
        );

        $this->assertSame(HttpStatusCodeEnum::HTTP_OK, $httpResponse->httpStatusCode);
        $this->assertSame('Hi there', $httpResponse->getContentString());
        $this->assertSame('text/plain; charset=utf-8', $httpResponse->getHeader(key: 'Content-Type'));
    }

    public function testPrepareHttpResponseWithSessionHandlerCreatesTheSession(): void
    {
        $sessionHandler = new NonStartingSessionHandler();
        $core = $this->createCore();

        $core->prepareHttpResponse(
            logger: new RecordingLogger(),
            routeCollection: new RouteCollection(routes: [$this->createTextRoute()]),
            individualSessionHandler: $sessionHandler,
        );

        $this->assertSame($sessionHandler, $core->sessionHandler);
        $this->assertNotNull($core->session);
        $this->assertNotNull($core->formContext->csrfTokenSource);
    }

    public function testSessionIsNotStartedIfNothingUsesIt(): void
    {
        $sessionHandler = new NonStartingSessionHandler();
        $core = $this->createCore();

        $core->prepareHttpResponse(
            logger: new RecordingLogger(),
            routeCollection: new RouteCollection(routes: [$this->createTextRoute()]),
            individualSessionHandler: $sessionHandler,
        );

        $this->assertSame(0, $sessionHandler->starts);
        $this->assertSame(0, $sessionHandler->closes);
        $this->assertFalse($sessionHandler->isClosed());
    }

    public function testStartedSessionIsClosedAfterTheViewAndBeforeTheResponse(): void
    {
        $_SESSION = [];
        $sessionHandler = new NonStartingSessionHandler();
        $core = $this->createCore();
        $closesDuringView = -1;
        $route = new Route(
            path: '/',
            viewDirectory: $this->workDirectory->viewDirectory,
            viewCallback: function () use ($core, $sessionHandler, &$closesDuringView): string {
                $core->session?->set(key: 'cart', value: 'full');
                $closesDuringView = $sessionHandler->closes;

                return 'Hello World!';
            },
            defaultContentType: ContentType::createTxt(),
        );

        $httpResponse = $core->prepareHttpResponse(
            logger: new RecordingLogger(),
            routeCollection: new RouteCollection(routes: [$route]),
            individualSessionHandler: $sessionHandler,
        );

        $this->assertSame('Hello World!', $httpResponse->getContentString());
        $this->assertSame(0, $closesDuringView);
        $this->assertSame(1, $sessionHandler->starts);
        $this->assertSame(1, $sessionHandler->closes);
        $this->assertTrue($sessionHandler->isClosed());
        $this->assertSame('full', $core->session?->getString(key: 'cart'));
    }

    public function testSessionCannotBeWrittenAfterTheResponseIsPrepared(): void
    {
        $_SESSION = [];
        $sessionHandler = new NonStartingSessionHandler();
        $core = $this->createCore();
        $route = new Route(
            path: '/',
            viewDirectory: $this->workDirectory->viewDirectory,
            viewCallback: function () use ($core): string {
                $core->session?->getString(key: 'cart');

                return 'Hello World!';
            },
            defaultContentType: ContentType::createTxt(),
        );
        $core->prepareHttpResponse(
            logger: new RecordingLogger(),
            routeCollection: new RouteCollection(routes: [$route]),
            individualSessionHandler: $sessionHandler,
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The session is closed: it cannot be changed any more.');

        $core->session?->set(key: 'cart', value: 'late');
    }

    public function testPrepareHttpResponseRedirectsAnInsecureRequestToHttps(): void
    {
        $core = $this->createCore(
            httpRequest: HttpRequestFactory::create(
                protocol: ProtocolEnum::HTTP,
                port: 80,
                uri: '/page.html?a=1',
            ),
        );

        $httpResponse = $core->prepareHttpResponse(
            logger: new RecordingLogger(),
            routeCollection: new RouteCollection(routes: [$this->createTextRoute()]),
            individualSessionHandler: false,
        );

        $this->assertSame(HttpStatusCodeEnum::HTTP_SEE_OTHER, $httpResponse->httpStatusCode);
        $this->assertSame('https://example.com/page.html?a=1', $httpResponse->getHeader(key: 'Location'));
        $this->assertNull($httpResponse->getContentString());
        // The redirect needs no exception handler, no session and no route
        $this->assertFalse($this->isExceptionHandlerRegistered());
    }

    public function testPrepareHttpResponseRedirectsWithoutAnyRoute(): void
    {
        $httpResponse = $this->createCore(
            httpRequest: HttpRequestFactory::create(protocol: ProtocolEnum::HTTP, port: 80),
        )->prepareHttpResponse();

        $this->assertSame(HttpStatusCodeEnum::HTTP_SEE_OTHER, $httpResponse->httpStatusCode);
    }

    public function testPrepareHttpResponseTwiceThrows(): void
    {
        $core = $this->createCore();
        $routeCollection = new RouteCollection(routes: [$this->createTextRoute()]);
        $core->prepareHttpResponse(
            logger: new RecordingLogger(),
            routeCollection: $routeCollection,
            individualSessionHandler: false,
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The HttpResponse is already prepared');

        $core->prepareHttpResponse(
            logger: new RecordingLogger(),
            routeCollection: $routeCollection,
            individualSessionHandler: false,
        );
    }

    public function testPrepareHttpResponseTwiceThrowsForTheRedirectToo(): void
    {
        $core = $this->createCore(
            httpRequest: HttpRequestFactory::create(protocol: ProtocolEnum::HTTP, port: 80),
        );
        $core->prepareHttpResponse();

        $this->expectException(LogicException::class);

        $core->prepareHttpResponse();
    }

    public function testPrepareHttpResponseWithoutRoutesThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('There must be at least one route');

        $this->createCore()->prepareHttpResponse(logger: new RecordingLogger(), individualSessionHandler: false);
    }

    public function testPrepareHttpResponseWithInvalidTemplateTagNameThrowsBeforeTheExceptionHandlerExists(): void
    {
        $core = $this->createCore();

        try {
            $core->prepareHttpResponse(
                logger: new RecordingLogger(),
                routeCollection: new RouteCollection(routes: [$this->createTextRoute()]),
                individualSessionHandler: false,
                templateTags: [new NamedTag(name: 'text')],
            );
            CoreTest::fail('An own tag with the name of a built-in tag must throw.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('"text"', $exception->getMessage());
        }
        $this->assertFalse($this->isExceptionHandlerRegistered());
    }

    public function testPrepareHttpResponseOfAnUnknownRouteThrowsAndTheExceptionHandlerAnswers404(): void
    {
        $core = $this->createCore(httpRequest: HttpRequestFactory::create(uri: '/nothing-here.html'));

        try {
            $core->prepareHttpResponse(
                logger: new RecordingLogger(),
                routeCollection: new RouteCollection(routes: [$this->createViewRoute(path: '/known/')]),
                individualSessionHandler: false,
            );
            CoreTest::fail('An unknown route must throw a NotFoundException.');
        } catch (NotFoundException $exception) {
            $handler = set_exception_handler(callback: null);
            restore_exception_handler();
            $this->assertIsArray($handler);
            $this->assertInstanceOf(ExceptionHandler::class, $handler[0]);
            $httpResponse = $handler[0]->createResponse(throwable: $exception);
        }

        $this->assertSame(HttpStatusCodeEnum::HTTP_NOT_FOUND, $httpResponse->httpStatusCode);
        $this->assertStringContainsString('Error page notFound.html', (string) $httpResponse->getContentString());
    }

    public function testCreateTemplateEngineUsesTheCacheDirectory(): void
    {
        $core = $this->createCore();
        $templateFile = $this->workDirectory->viewDirectory . 'frontend/html/sample.html';

        $engine = $core->createTemplateEngine(
            localeHandler: new LocaleHandler(
                language: null,
                availableLanguages: new LanguageCollection(),
            ),
        );
        $output = $engine->render(
            templateFile: $templateFile,
            data: TemplateData::fromReplacements(
                replacements: new HtmlReplacementCollection(),
            ),
        );

        $this->assertSame('<p>Hello</p>', $output);
        $this->assertNotSame([], glob(pattern: $core->cacheDirectory . '*'));
    }
}
