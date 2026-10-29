<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit;

use actra\yuf\App;
use actra\yuf\Core;
use actra\yuf\core\BaseView;
use actra\yuf\core\ContentType;
use actra\yuf\core\HtmlFileExtensionEnum;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\Language;
use actra\yuf\core\ResponseSender;
use actra\yuf\core\Route;
use actra\yuf\core\ViewContext;
use actra\yuf\core\ViewMap;
use actra\yuf\exception\ExceptionHandler;
use actra\yuf\exception\NotFoundException;
use actra\yuf\layout\NavigationItemCollection;
use actra\yuf\tests\Double\core\CoreWorkDirectory;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\core\RecordingLogger;
use actra\yuf\tests\Double\core\RecordingResponseSender;
use actra\yuf\tests\Double\core\TestView;
use actra\yuf\tests\Double\session\NonStartingSessionHandler;
use actra\yuf\tests\Double\template\NamedTag;
use Closure;
use InvalidArgumentException;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * Builds `App` with `fromCore()` around a `Core` of temporary directories. Not covered: `App::fromEnvironment()`
 * and `run()` (globals, once per process; `run()` is `prepare()->sendAndExit()`).
 */
final class AppTest extends TestCase
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
        $handler = set_exception_handler(callback: null);
        restore_exception_handler();
        if (is_array(value: $handler) && $handler[0] instanceof ExceptionHandler) {
            restore_exception_handler();
        }
        $this->workDirectory->cleanUp();
    }

    /**
     * Without session and with a recording logger, so the tests write no files; `CoreWorkDirectory` has the view
     * `frontend/html/sample.html` without view class.
     */
    private function createApp(
        string $uri = '/sample.html',
        string $acceptLanguage = '',
        ResponseSender $responseSender = new RecordingResponseSender(),
    ): App {
        return App::fromCore(
            core: new Core(
                settings: $this->workDirectory->createSettings(),
                httpRequest: HttpRequestFactory::create(
                    uri: $uri,
                    headers: $acceptLanguage === '' ? [] : ['Accept-Language' => $acceptLanguage],
                ),
                responseSender: $responseSender,
            ),
            viewClassPrefix: 'actra\yuf\tests\Double',
        )->withoutSession()->withLogger(logger: new RecordingLogger());
    }

    /**
     * The redirects are sent by the request handler, before `prepare()` returns.
     *
     * @param Closure(App): App $configure
     */
    private function prepareRedirect(Closure $configure, string $uri = '/', string $acceptLanguage = ''): HttpResponse
    {
        return RecordingResponseSender::capture(
            action: function (ResponseSender $responseSender) use ($configure, $uri, $acceptLanguage): void {
                $configure(
                    $this->createApp(uri: $uri, acceptLanguage: $acceptLanguage, responseSender: $responseSender),
                )->prepare();
            },
        );
    }

    public function testWithoutRoutesServesTheFrontendViewGroupAtTheRoot(): void
    {
        $httpResponse = $this->createApp()->prepare();

        $this->assertSame(HttpStatusCodeEnum::HTTP_OK, $httpResponse->httpStatusCode);
        $this->assertSame('<p>Hello</p>', $httpResponse->getContentString());
        $this->assertSame('text/html; charset=utf-8', $httpResponse->getHeader(key: 'Content-Type'));
        $this->assertNotNull($httpResponse->getHeader(key: 'Content-Security-Policy'));
    }

    public function testHtmlRouteServesItsViewGroup(): void
    {
        $httpResponse = $this->createApp(uri: '/pages/sample.html')
            ->withHtmlRoute(path: '/pages/', viewGroup: 'frontend')
            ->prepare();

        $this->assertSame('<p>Hello</p>', $httpResponse->getContentString());
    }

    public function testHtmlRouteAcceptsHtmlFilesOnly(): void
    {
        $app = $this->createApp(uri: '/sample.txt')->withHtmlRoute(path: '/', viewGroup: 'frontend');

        $this->expectException(NotFoundException::class);

        $app->prepare();
    }

    public function testHtmlRouteWithoutExtensionServesPathsWithoutExtension(): void
    {
        $httpResponse = $this->createApp(uri: '/sample')
            ->withHtmlRoute(path: '/', viewGroup: 'frontend', fileExtension: HtmlFileExtensionEnum::NONE)
            ->prepare();

        $this->assertSame('<p>Hello</p>', $httpResponse->getContentString());
        $this->assertSame('text/html; charset=utf-8', $httpResponse->getHeader(key: 'Content-Type'));
    }

    public function testHtmlRouteWithoutExtensionServesTheDefaultFileTitleAtItsPath(): void
    {
        $httpResponse = $this->createApp(uri: '/pages/')
            ->withHtmlRoute(
                path: '/pages/',
                viewGroup: 'frontend',
                defaultFileTitle: 'sample',
                fileExtension: HtmlFileExtensionEnum::NONE,
            )
            ->prepare();

        $this->assertSame('<p>Hello</p>', $httpResponse->getContentString());
    }

    public function testHtmlRouteWithoutExtensionRejectsHtmlFiles(): void
    {
        $app = $this->createApp()
            ->withHtmlRoute(path: '/', viewGroup: 'frontend', fileExtension: HtmlFileExtensionEnum::NONE);

        $this->expectException(NotFoundException::class);

        $app->prepare();
    }

    public function testHtmlRouteRejectsPathsWithoutExtension(): void
    {
        $app = $this->createApp(uri: '/sample')->withHtmlRoute(path: '/', viewGroup: 'frontend');

        $this->expectException(NotFoundException::class);

        $app->prepare();
    }

    public function testHtmlRouteServesTheDefaultFileTitleAtItsPath(): void
    {
        $httpResponse = $this->createApp(uri: '/pages/')
            ->withHtmlRoute(path: '/pages/', viewGroup: 'frontend', defaultFileTitle: 'sample')
            ->prepare();

        $this->assertSame('<p>Hello</p>', $httpResponse->getContentString());
    }

    public function testDefaultFileNameIsRedirectedToThePathOfTheRoute(): void
    {
        $redirect = $this->prepareRedirect(
            configure: static fn(App $app): App => $app
                ->withHtmlRoute(path: '/pages/', viewGroup: 'frontend', defaultFileTitle: 'sample'),
            uri: '/pages/sample.html',
        );

        $this->assertSame(HttpStatusCodeEnum::HTTP_MOVED_PERMANENTLY, $redirect->httpStatusCode);
        $this->assertSame('https://example.com/pages/', $redirect->getHeader(key: 'Location'));
    }

    public function testDefaultFileTitleWithoutExtensionIsRedirectedToThePathOfTheRoute(): void
    {
        $redirect = $this->prepareRedirect(
            configure: static fn(App $app): App => $app->withHtmlRoute(
                path: '/',
                viewGroup: 'frontend',
                defaultFileTitle: 'sample',
                fileExtension: HtmlFileExtensionEnum::NONE,
            ),
            uri: '/sample',
        );

        $this->assertSame('https://example.com/', $redirect->getHeader(key: 'Location'));
    }

    public function testDefaultFileNameIsServedWithoutRedirectIfDisabled(): void
    {
        $httpResponse = $this->createApp(uri: '/pages/sample.html')
            ->withHtmlRoute(
                path: '/pages/',
                viewGroup: 'frontend',
                defaultFileTitle: 'sample',
                redirectDefaultFileName: false,
            )
            ->prepare();

        $this->assertSame('<p>Hello</p>', $httpResponse->getContentString());
    }

    public function testHtmlRouteUsesTheViewFactory(): void
    {
        $created = false;
        $app = $this->createApp()->withHtmlRoute(
            path: '/',
            viewGroup: 'frontend',
            viewFactory: new ViewMap()->add(
                fileTitle: 'sample',
                create: static function (ViewContext $context) use (&$created): BaseView {
                    $created = true;

                    return new TestView(context: $context);
                },
            ),
        );

        $app->prepare();

        $this->assertTrue($created);
    }

    public function testFileRouteAcceptsTheExtensionOfItsContentTypeOnly(): void
    {
        $app = $this->createApp(uri: '/export/sample.html')
            ->withFileRoute(path: '/export/', viewGroup: 'frontend', contentType: ContentType::createCsv());

        $this->expectException(NotFoundException::class);

        $app->prepare();
    }

    public function testLanguagesAreAddedToCore(): void
    {
        $app = $this->createApp()
            ->withLanguage(code: 'de', locale: 'de_CH.UTF-8')
            ->withLanguage(code: 'en', locale: 'en_US.UTF-8');

        $app->prepare();

        $this->assertSame($app->getLanguage(code: 'de'), $app->core->availableLanguages->getLanguageByCode(
            languageCode: 'de',
        ));
        $this->assertSame($app->getLanguage(code: 'en'), $app->core->availableLanguages->getLanguageByCode(
            languageCode: 'en',
        ));
    }

    public function testRootIsRedirectedToTheFirstHtmlRouteOfTheFirstLanguage(): void
    {
        $redirect = $this->prepareRedirect(
            configure: static fn(App $app): App => $app
                ->withLanguage(code: 'de', locale: 'de_CH.UTF-8')
                ->withFileRoute(path: '/export/', viewGroup: 'frontend', contentType: ContentType::createCsv())
                ->withHtmlRoute(path: '/de/', viewGroup: 'frontend')
                ->withHtmlRoute(path: '/backend/', viewGroup: 'frontend'),
        );

        $this->assertSame('https://example.com/de/', $redirect->getHeader(key: 'Location'));
    }

    public function testRootIsRedirectedToTheHtmlRouteOfTheBrowserLanguage(): void
    {
        $redirect = $this->prepareRedirect(
            configure: static fn(App $app): App => $app
                ->withLanguage(code: 'de', locale: 'de_CH.UTF-8')
                ->withLanguage(code: 'en', locale: 'en_US.UTF-8')
                ->withHtmlRoute(path: '/de/', viewGroup: 'frontend')
                ->withHtmlRoute(path: '/en/', viewGroup: 'frontend', languageCode: 'en'),
            acceptLanguage: 'en',
        );

        $this->assertSame('https://example.com/en/', $redirect->getHeader(key: 'Location'));
    }

    public function testOwnRouteWithTheLanguageOfTheApp(): void
    {
        $app = $this->createApp(uri: '/hello.txt')->withLanguage(code: 'de', locale: 'de_CH.UTF-8');
        $app = $app->withRoute(
            route: new Route(
                path: '/',
                viewDirectory: $app->core->viewDirectory,
                viewCallback: static fn(): string => 'Hallo',
                defaultContentType: ContentType::createTxt(),
                language: $app->getLanguage(code: 'de'),
            ),
        );

        $this->assertSame('Hallo', $app->prepare()->getContentString());
    }

    public function testLanguageAfterARouteThrows(): void
    {
        $app = $this->createApp()->withHtmlRoute(path: '/', viewGroup: 'frontend');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs(
            'Add the language "de" with withLanguage() before the routes: the routes take the first language as'
            . ' their language.',
        );

        $app->withLanguage(code: 'de', locale: 'de_CH.UTF-8');
    }

    public function testLanguageTwiceThrows(): void
    {
        $app = $this->createApp()->withLanguage(code: 'de', locale: 'de_CH.UTF-8');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The language "de" is already added.');

        $app->withLanguage(code: 'de', locale: 'de_DE.UTF-8');
    }

    public function testRouteOfAnUnknownLanguageThrows(): void
    {
        $app = $this->createApp()->withLanguage(code: 'de', locale: 'de_CH.UTF-8');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs(
            'The language "fr" is not added: call withLanguage(code: \'fr\', locale: …) first.',
        );

        $app->withHtmlRoute(path: '/fr/', viewGroup: 'frontend', languageCode: 'fr');
    }

    public function testOwnRouteWithAnotherLanguageObjectThrows(): void
    {
        $app = $this->createApp()->withLanguage(code: 'de', locale: 'de_CH.UTF-8');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs(
            'The language "de" of the route "/" is not added: call withLanguage() first and give the route'
            . ' $app->getLanguage(code: \'de\').',
        );

        $app->withRoute(
            route: new Route(
                path: '/',
                viewDirectory: $app->core->viewDirectory,
                language: new Language(code: 'de', locale: 'de_CH.UTF-8'),
            ),
        );
    }

    public function testSecondDefaultRouteOfALanguageThrows(): void
    {
        $app = $this->createApp()
            ->withLanguage(code: 'de', locale: 'de_CH.UTF-8')
            ->withHtmlRoute(path: '/', viewGroup: 'frontend');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs(
            'The language "de" has a default route already, the route "/de/" cannot be the default too.',
        );

        $app->withRoute(
            route: new Route(
                path: '/de/',
                viewDirectory: $app->core->viewDirectory,
                isDefaultForLanguage: true,
                language: $app->getLanguage(code: 'de'),
            ),
        );
    }

    public function testSamePathTwiceThrows(): void
    {
        $app = $this->createApp()->withHtmlRoute(path: '/', viewGroup: 'frontend');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('There is already a route with the path "/".');

        $app->withHtmlRoute(path: '/', viewGroup: 'backend');
    }

    public function testWithCspNullSendsNoCspHeader(): void
    {
        $httpResponse = $this->createApp()->withCsp(cspPolicySettings: null)->prepare();

        $this->assertNull($httpResponse->getHeader(key: 'Content-Security-Policy'));
    }

    public function testWithoutSessionHasNoSession(): void
    {
        $app = $this->createApp();

        $app->prepare();

        $this->assertNull($app->core->sessionHandler);
        $this->assertNull($app->core->session);
    }

    public function testWithSessionHandlerUsesTheHandler(): void
    {
        $sessionHandler = new NonStartingSessionHandler();
        $app = $this->createApp()->withSessionHandler(sessionHandler: $sessionHandler);

        $app->prepare();

        $this->assertSame($sessionHandler, $app->core->sessionHandler);
        $this->assertNotNull($app->core->session);
    }

    public function testWithMethodsReturnACopy(): void
    {
        $sessionHandler = new NonStartingSessionHandler();
        $app = $this->createApp()->withSessionHandler(sessionHandler: $sessionHandler);

        $app->withoutSession();
        $app->withHtmlRoute(path: '/other/', viewGroup: 'frontend');
        $app->prepare();

        $this->assertSame($sessionHandler, $app->core->sessionHandler);
    }

    public function testInvalidLoginPathThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The login path must be a local path starting with "/"');

        $this->createApp()->withLoginPath(loginPath: 'https://evil.example/login/');
    }

    public function testTemplateTagsArePassedToCore(): void
    {
        $app = $this->createApp()->withTemplateTags(templateTags: [new NamedTag(name: 'text')]);

        $this->expectException(InvalidArgumentException::class);

        $app->prepare();
    }

    public function testNavigationIsPassedToTheViews(): void
    {
        $navigation = null;
        $app = $this->createApp()
            ->withNavigation(
                navigationProvider: static fn(ViewContext $context): NavigationItemCollection
                    => new NavigationItemCollection(),
            )
            ->withHtmlRoute(
                path: '/',
                viewGroup: 'frontend',
                viewFactory: new ViewMap()->add(
                    fileTitle: 'sample',
                    create: static function (ViewContext $context) use (&$navigation): BaseView {
                        $navigation = $context->getNavigation();

                        return new TestView(context: $context);
                    },
                ),
            );

        $app->prepare();

        $this->assertInstanceOf(NavigationItemCollection::class, $navigation);
    }

    public function testPrepareTwiceThrows(): void
    {
        $app = $this->createApp();
        $app->prepare();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The HttpResponse is already prepared');

        $app->prepare();
    }
}
