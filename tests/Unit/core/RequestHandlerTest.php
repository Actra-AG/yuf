<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\Language;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\PathVars;
use actra\yuf\core\RequestHandler;
use actra\yuf\core\Route;
use actra\yuf\core\RouteCollection;
use actra\yuf\exception\NotFoundException;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Not covered: redirects of "/" (they exit) and the preferred language in the session.
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
        );
    }

    private function createRequestHandler(
        string $requestUri,
        RouteCollection $routeCollection,
        LanguageCollection $availableLanguages = new LanguageCollection(),
        ?string $allowedDomain = null,
        string $host = 'example.com',
    ): RequestHandler {
        return new RequestHandler(
            httpRequest: HttpRequestFactory::create(host: $host, uri: $requestUri),
            routeCollection: $routeCollection,
            availableLanguages: $availableLanguages,
            allowedDomains: [$allowedDomain ?? $host],
            session: null,
        );
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
            self::fail('NotFoundException expected');
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
}
