<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\Language;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\NativeResponseSender;
use actra\yuf\core\PathVars;
use actra\yuf\core\RequestHandler;
use actra\yuf\core\ResponseSender;
use actra\yuf\core\Route;
use actra\yuf\core\RouteCollection;
use actra\yuf\exception\NotFoundException;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\core\RecordingResponseSender;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Not covered: redirects of "/" and the preferred language in the session.
 */
final class RequestHandlerTest extends TestCase
{
    private function createRoute(
        string $path,
        ?Language $language = null,
        bool $isDefaultForLanguage = false,
        ?string $acceptedExtension = null,
        ?string $forceFileGroup = null,
        ?string $forceFileName = null,
        bool $redirectDefaultFileName = false,
    ): Route {
        return new Route(
            path: $path,
            viewDirectory: '/tmp/views/',
            defaultFileName: 'index.html',
            isDefaultForLanguage: $isDefaultForLanguage,
            language: $language,
            acceptedExtension: $acceptedExtension,
            forceFileGroup: $forceFileGroup,
            forceFileName: $forceFileName,
            redirectDefaultFileName: $redirectDefaultFileName,
        );
    }

    private function createRequestHandler(
        string $requestUri,
        RouteCollection $routeCollection,
        LanguageCollection $availableLanguages = new LanguageCollection(),
        ?string $allowedDomain = null,
        string $host = 'example.com',
        ResponseSender $responseSender = new NativeResponseSender(),
        string $queryString = '',
    ): RequestHandler {
        return new RequestHandler(
            httpRequest: HttpRequestFactory::create(host: $host, uri: $requestUri, queryString: $queryString),
            routeCollection: $routeCollection,
            availableLanguages: $availableLanguages,
            allowedDomains: [$allowedDomain ?? $host],
            session: null,
            responseSender: $responseSender,
        );
    }

    public function testDefaultFileNameIsRedirectedPermanentlyToThePathOfTheRoute(): void
    {
        $route = $this->createRoute(path: '/shop/', redirectDefaultFileName: true);

        $redirect = RecordingResponseSender::capture(
            action: function (ResponseSender $responseSender) use ($route): void {
                $this->createRequestHandler(
                    requestUri: '/shop/index.html',
                    routeCollection: new RouteCollection(routes: [$route]),
                    responseSender: $responseSender,
                    queryString: 'page=2',
                )->resolveRoute();
            },
        );

        $this->assertSame(HttpStatusCodeEnum::HTTP_MOVED_PERMANENTLY, $redirect->httpStatusCode);
        $this->assertSame('https://example.com/shop/?page=2', $redirect->getHeader(key: 'Location'));
    }

    public function testOtherFileNamesAreNotRedirected(): void
    {
        $route = $this->createRoute(path: '/shop/', redirectDefaultFileName: true);

        $resolvedRoute = $this->createRequestHandler(
            requestUri: '/shop/index-2.html',
            routeCollection: new RouteCollection(routes: [$route]),
        )->resolveRoute();

        $this->assertSame('index-2.html', $resolvedRoute->fileName);
    }

    public function testDefaultFileNameIsNotRedirectedWithoutTheOption(): void
    {
        $resolvedRoute = $this->createRequestHandler(
            requestUri: '/shop/index.html',
            routeCollection: new RouteCollection(routes: [$this->createRoute(path: '/shop/')]),
        )->resolveRoute();

        $this->assertSame('index.html', $resolvedRoute->fileName);
    }

    public function testDefaultFileNameIsNotRedirectedForAForcedFileName(): void
    {
        $route = $this->createRoute(path: '/shop/', forceFileName: 'index.html', redirectDefaultFileName: true);

        $resolvedRoute = $this->createRequestHandler(
            requestUri: '/shop/index.html',
            routeCollection: new RouteCollection(routes: [$route]),
        )->resolveRoute();

        $this->assertSame('index.html', $resolvedRoute->fileName);
    }

    public function testDefaultFileNameIsNotRedirectedForARouteWithPathVariables(): void
    {
        $route = $this->createRoute(path: '/shop/${fileName}', redirectDefaultFileName: true);

        $resolvedRoute = $this->createRequestHandler(
            requestUri: '/shop/index.html',
            routeCollection: new RouteCollection(routes: [$route]),
        )->resolveRoute();

        $this->assertSame('index.html', $resolvedRoute->fileName);
    }

    public function testConstructorSetsTheFirstAvailableLanguageAndTheDefaultRoutes(): void
    {
        $german = new Language(code: 'de', locale: 'de_CH.UTF-8');
        $english = new Language(code: 'en', locale: 'en_US.UTF-8');
        $germanRoute = $this->createRoute(path: '/de/', language: $german, isDefaultForLanguage: true);
        $englishRoute = $this->createRoute(path: '/en/', language: $english, isDefaultForLanguage: true);
        $otherRoute = $this->createRoute(path: '/other/', language: $english);

        $handler = $this->createRequestHandler(
            requestUri: '/nope/x.html',
            routeCollection: new RouteCollection(routes: [$germanRoute, $englishRoute, $otherRoute]),
            availableLanguages: new LanguageCollection(languages: [$german, $english]),
        );

        $this->assertSame($german, $handler->language);
        $this->assertSame('x.html', $handler->fileName);
        $this->assertSame(['', 'nope', 'x.html'], $handler->pathParts);
        $this->assertSame([$germanRoute, $englishRoute], $handler->defaultRoutesByLanguage->routes);
        $this->assertSame('/de/', $handler->getLanguageRoot());
    }

    public function testConstructorWithoutLanguages(): void
    {
        $handler = $this->createRequestHandler(
            requestUri: '/index.html',
            routeCollection: new RouteCollection(routes: [$this->createRoute(path: '/')]),
        );

        $this->assertNull($handler->language);
        $this->assertSame('/', $handler->getLanguageRoot());
    }

    public function testResolveRouteSplitsTheFileName(): void
    {
        $route = $this->createRoute(path: '/');
        $handler = $this->createRequestHandler(
            requestUri: '/detail-12-a__DASH__b.html?x=1',
            routeCollection: new RouteCollection(routes: [$route]),
        );

        $resolved = $handler->resolveRoute();

        $this->assertSame($route, $resolved->route);
        $this->assertSame('detail-12-a__DASH__b.html', $handler->fileName);
        $this->assertSame('detail-12-a__DASH__b.html', $resolved->fileName);
        $this->assertSame('detail', $resolved->fileTitle);
        $this->assertSame('html', $resolved->fileExtension);
        $this->assertSame(['detail', '12', 'a-b'], $resolved->pathVars);
        $this->assertSame('12', new PathVars(values: $resolved->pathVars)->get(nr: 1));
    }

    public function testResolveRouteUsesTheDefaultFileNameAndTheRouteLanguage(): void
    {
        $german = new Language(code: 'de', locale: 'de_CH.UTF-8');
        $english = new Language(code: 'en', locale: 'en_US.UTF-8');
        $handler = $this->createRequestHandler(
            requestUri: '/en/',
            routeCollection: new RouteCollection(routes: [$this->createRoute(path: '/en/', language: $english)]),
            availableLanguages: new LanguageCollection(languages: [$german, $english]),
        );

        $resolved = $handler->resolveRoute();

        $this->assertSame($english, $handler->language);
        $this->assertSame($english, $resolved->language);
        $this->assertSame('index.html', $handler->fileName);
        $this->assertSame('index.html', $resolved->fileName);
        $this->assertSame('index', $resolved->fileTitle);
    }

    public function testResolveRouteThrowsForAnUnknownRoute(): void
    {
        $handler = $this->createRequestHandler(
            requestUri: '/nope/x.html',
            routeCollection: new RouteCollection(routes: [$this->createRoute(path: '/')]),
        );

        $this->expectException(NotFoundException::class);
        $handler->resolveRoute();
    }

    public function testResolveRouteThrowsForADomainThatIsNotAllowed(): void
    {
        $handler = $this->createRequestHandler(
            requestUri: '/index.html',
            routeCollection: new RouteCollection(routes: [$this->createRoute(path: '/')]),
            allowedDomain: 'other.example',
        );

        $this->expectException(NotFoundException::class);
        $handler->resolveRoute();
    }

    public function testResolveRouteThrowsForDoubleSlashes(): void
    {
        $handler = $this->createRequestHandler(
            requestUri: '/a//index.html',
            routeCollection: new RouteCollection(routes: [$this->createRoute(path: '/')]),
        );

        $this->expectException(NotFoundException::class);
        $handler->resolveRoute();
    }

    public function testResolveRouteTwiceThrows(): void
    {
        $handler = $this->createRequestHandler(
            requestUri: '/index.html',
            routeCollection: new RouteCollection(routes: [$this->createRoute(path: '/')]),
        );
        $handler->resolveRoute();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The route is already resolved');
        $handler->resolveRoute();
    }

    public function testTheLanguageAndTheFileNameSurviveAnUnknownRoute(): void
    {
        $english = new Language(code: 'en', locale: 'en_US.UTF-8');
        $handler = $this->createRequestHandler(
            requestUri: '/nope/x.html',
            routeCollection: new RouteCollection(
                routes: [$this->createRoute(path: '/en/', language: $english, isDefaultForLanguage: true)],
            ),
            availableLanguages: new LanguageCollection(languages: [$english]),
        );
        try {
            $handler->resolveRoute();
        } catch (NotFoundException) {
        }

        $this->assertSame($english, $handler->language);
        $this->assertSame('x.html', $handler->fileName);
        $this->assertSame('/en/', $handler->getLanguageRoot());
    }

    public function testTheHostIsComparedWithThePortTheClientSent(): void
    {
        $handler = $this->createRequestHandler(
            requestUri: '/index.html',
            routeCollection: new RouteCollection(routes: [$this->createRoute(path: '/')]),
            allowedDomain: 'example.com',
            host: 'example.com:8443',
        );

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessageIs('example.com:8443 is not set as allowed domain in your environment settings.');
        $handler->resolveRoute();
    }

    public function testTheQueryStringIsNotPartOfThePath(): void
    {
        $handler = $this->createRequestHandler(
            requestUri: '/detail-5.html?a=//b',
            routeCollection: new RouteCollection(routes: [$this->createRoute(path: '/')]),
        );

        $resolved = $handler->resolveRoute();

        $this->assertSame('detail-5.html', $handler->fileName);
        $this->assertSame('detail-5.html', $resolved->fileName);
    }

    public function testResolveRouteTakesTheVariablesOfAPathPattern(): void
    {
        $route = $this->createRoute(path: '/shop/${fileGroup}/${fileName}/${id}');
        $handler = $this->createRequestHandler(
            requestUri: '/shop/books/detail-7.html/42',
            routeCollection: new RouteCollection(routes: [$route]),
        );

        $resolved = $handler->resolveRoute();

        $this->assertSame($route, $resolved->route);
        $this->assertSame('books', $resolved->fileGroup);
        $this->assertSame('detail-7.html', $handler->fileName);
        $this->assertSame('detail-7.html', $resolved->fileName);
        $this->assertSame('detail', $resolved->fileTitle);
        $this->assertSame(['id' => '42'], $resolved->routeVariables);
    }

    public function testTextBetweenTheVariablesOfAPathPatternIsLiteral(): void
    {
        $route = $this->createRoute(path: '/v1.0/${fileName}');
        $matching = $this->createRequestHandler(
            requestUri: '/v1.0/list.json',
            routeCollection: new RouteCollection(routes: [$route]),
        );
        $notMatching = $this->createRequestHandler(
            requestUri: '/v1x0/list.json',
            routeCollection: new RouteCollection(routes: [$route]),
        );

        $this->assertSame('list.json', $matching->resolveRoute()->fileName);
        $this->expectException(NotFoundException::class);
        $notMatching->resolveRoute();
    }

    public function testResolveRouteWithoutPathPatternHasNoGroupAndNoVariables(): void
    {
        $handler = $this->createRequestHandler(
            requestUri: '/index.html',
            routeCollection: new RouteCollection(routes: [$this->createRoute(path: '/')]),
        );

        $resolved = $handler->resolveRoute();

        $this->assertNull($resolved->fileGroup);
        $this->assertSame([], $resolved->routeVariables);
    }

    public function testResolveRouteLetsTheRouteForceTheFileGroupAndTheFileName(): void
    {
        $handler = $this->createRequestHandler(
            requestUri: '/x/other.html',
            routeCollection: new RouteCollection(
                routes: [$this->createRoute(path: '/x/', forceFileGroup: 'forced', forceFileName: 'fixed-1.php')],
            ),
        );

        $resolved = $handler->resolveRoute();

        $this->assertSame('forced', $resolved->fileGroup);
        $this->assertSame('fixed-1.php', $handler->fileName);
        $this->assertSame('fixed-1.php', $resolved->fileName);
        $this->assertSame('fixed', $resolved->fileTitle);
        $this->assertSame('php', $resolved->fileExtension);
    }

    public function testResolveRouteWithoutExtensionHasAnEmptyFileExtension(): void
    {
        $handler = $this->createRequestHandler(
            requestUri: '/detail',
            routeCollection: new RouteCollection(routes: [$this->createRoute(path: '/')]),
        );

        $resolved = $handler->resolveRoute();

        $this->assertSame('detail', $handler->fileName);
        $this->assertSame('detail', $resolved->fileName);
        $this->assertSame('', $resolved->fileExtension);
    }

    public function testResolveRouteAcceptsTheAcceptedExtension(): void
    {
        $handler = $this->createRequestHandler(
            requestUri: '/index.json',
            routeCollection: new RouteCollection(
                routes: [$this->createRoute(path: '/', acceptedExtension: 'json')],
            ),
        );

        $resolved = $handler->resolveRoute();

        $this->assertSame('json', $resolved->fileExtension);
    }

    public function testAnExtensionThatIsNotAcceptedIsNotFoundButTheResolvedStateStaysForTheErrorPage(): void
    {
        $german = new Language(code: 'de', locale: 'de_CH.UTF-8');
        $english = new Language(code: 'en', locale: 'en_US.UTF-8');
        $handler = $this->createRequestHandler(
            requestUri: '/en/',
            routeCollection: new RouteCollection(
                routes: [
                    $this->createRoute(path: '/de/', language: $german, isDefaultForLanguage: true),
                    $this->createRoute(
                        path: '/en/',
                        language: $english,
                        isDefaultForLanguage: true,
                        acceptedExtension: 'json',
                    ),
                ],
            ),
            availableLanguages: new LanguageCollection(languages: [$german, $english]),
        );
        try {
            $handler->resolveRoute();
            RequestHandlerTest::fail('NotFoundException expected');
        } catch (NotFoundException) {
        }

        $this->assertSame($english, $handler->language);
        $this->assertSame('index.html', $handler->fileName);
        $this->assertSame('/en/', $handler->getLanguageRoot());
    }

    public function testTheLanguageOfTheErrorPageIsTheInitialOneForARouteWithoutLanguage(): void
    {
        $german = new Language(code: 'de', locale: 'de_CH.UTF-8');
        $handler = $this->createRequestHandler(
            requestUri: '/other/x.html',
            routeCollection: new RouteCollection(
                routes: [
                    $this->createRoute(path: '/de/', language: $german, isDefaultForLanguage: true),
                    $this->createRoute(path: '/other/'),
                ],
            ),
            availableLanguages: new LanguageCollection(languages: [$german]),
        );

        $resolved = $handler->resolveRoute();

        $this->assertSame($german, $handler->language);
        $this->assertSame($german, $resolved->language);
        $this->assertSame('x.html', $handler->fileName);
        $this->assertSame('/de/', $handler->getLanguageRoot());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidFileNameProvider(): iterable
    {
        yield 'parent directories' => ['/shop/../../../evil/x.html'];
        yield 'parent directory in the title' => ['/shop/..%2f..%2fevil%2fx.html'];
        yield 'slash in the title' => ['/shop/a/b.html'];
        yield 'backslash' => ['/shop/a\\b.html'];
        yield 'null byte' => ['/shop/a%00b.html'];
        yield 'space' => ['/shop/a b.html'];
    }

    #[DataProvider('invalidFileNameProvider')]
    public function testAFileTitleOutsideOfLettersDigitsAndUnderscoreIsNotFound(string $requestUri): void
    {
        $handler = $this->createRequestHandler(
            requestUri: $requestUri,
            routeCollection: new RouteCollection(routes: [$this->createRoute(path: '/shop/${fileName}')]),
        );

        $this->expectException(NotFoundException::class);

        $handler->resolveRoute();
    }

    public function testAValidFileTitleWithPathVariablesIsResolved(): void
    {
        $handler = $this->createRequestHandler(
            requestUri: '/shop/Detail_2-12-x.html',
            routeCollection: new RouteCollection(routes: [$this->createRoute(path: '/shop/${fileName}')]),
        );

        $this->assertSame('Detail_2', $handler->resolveRoute()->fileTitle);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidFileGroupProvider(): iterable
    {
        yield 'parent directory' => ['/shop/../x/index.html'];
        yield 'parent directory at the end' => ['/shop/a/../index.html'];
        yield 'encoded parent directory' => ['/shop/..%2f..%2fx/index.html'];
        yield 'dot segment' => ['/shop/./index.html'];
        yield 'backslash' => ['/shop/a\\b/index.html'];
        yield 'empty group' => ['/shop//index.html'];
    }

    #[DataProvider('invalidFileGroupProvider')]
    public function testAFileGroupWithOtherThanNamesIsNotFound(string $requestUri): void
    {
        $handler = $this->createRequestHandler(
            requestUri: $requestUri,
            routeCollection: new RouteCollection(
                routes: [$this->createRoute(path: '/shop/${fileGroup}/${fileName}')],
            ),
        );

        $this->expectException(NotFoundException::class);

        $handler->resolveRoute();
    }

    public function testAFileGroupWithSeveralNamesIsResolved(): void
    {
        $handler = $this->createRequestHandler(
            requestUri: '/shop/a_b/C-1/index.html',
            routeCollection: new RouteCollection(
                routes: [$this->createRoute(path: '/shop/${fileGroup}/${fileName}')],
            ),
        );

        $this->assertSame('a_b/C-1', $handler->resolveRoute()->fileGroup);
    }
}
