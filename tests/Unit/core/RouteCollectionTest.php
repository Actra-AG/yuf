<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\Language;
use actra\yuf\core\Route;
use actra\yuf\core\RouteCollection;
use LogicException;
use PHPUnit\Framework\TestCase;

final class RouteCollectionTest extends TestCase
{
    private function createRoute(string $path, ?Language $language = null): Route
    {
        return new Route(
            path: $path,
            viewDirectory: '/tmp/views/',
            language: $language,
        );
    }


    public function testEmptyCollectionHasNoRoutes(): void
    {
        $collection = new RouteCollection();

        $this->assertFalse($collection->hasRoutes());
        $this->assertSame([], $collection->routes);
    }

    public function testConstructorKeepsRoutesInOrder(): void
    {
        $first = $this->createRoute(path: '/a/');
        $second = $this->createRoute(path: '/b/');
        $third = $this->createRoute(path: '/c/');

        $collection = new RouteCollection(routes: [$first, $second, $third]);

        $this->assertTrue($collection->hasRoutes());
        $this->assertSame([$first, $second, $third], $collection->routes);
        $this->assertSame($first, $collection->getFirstRoute());
    }

    public function testAddRouteAppendsInOrder(): void
    {
        $first = $this->createRoute(path: '/a/');
        $second = $this->createRoute(path: '/b/');
        $collection = new RouteCollection();

        $collection->addRoute(route: $first);
        $collection->addRoute(route: $second);

        $this->assertTrue($collection->hasRoutes());
        $this->assertSame([$first, $second], $collection->routes);
        $this->assertSame($first, $collection->getFirstRoute());
    }

    public function testGetRouteForLanguageFindsMatchingRoute(): void
    {
        $german = $this->createRoute(
            path: '/de/',
            language: new Language(code: 'de', locale: 'de_CH'),
        );
        $english = $this->createRoute(
            path: '/en/',
            language: new Language(code: 'en', locale: 'en_GB'),
        );
        $collection = new RouteCollection(routes: [$german, $english]);

        $this->assertSame($english, $collection->getRouteForLanguage(languageCode: 'en'));
        $this->assertSame($german, $collection->getRouteForLanguage(languageCode: 'de'));
        $this->assertNull($collection->getRouteForLanguage(languageCode: 'fr'));
    }

    public function testDuplicatePathThrows(): void
    {
        $path = '/duplicate/';

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('There is already a route with this path: ' . $path);

        $collection = new RouteCollection();
        $collection->addRoute(route: $this->createRoute(path: $path));
        $collection->addRoute(route: $this->createRoute(path: $path));
    }

    public function testDuplicatePathInConstructorThrows(): void
    {
        $path = '/duplicate/';

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('There is already a route with this path: ' . $path);

        new RouteCollection(routes: [
            $this->createRoute(path: $path),
            $this->createRoute(path: $path),
        ]);
    }

    public function testSamePathInDifferentCollectionsDoesNotThrow(): void
    {
        $path = '/shared/';
        $first = $this->createRoute(path: $path);
        $second = $this->createRoute(path: $path);

        $firstCollection = new RouteCollection(routes: [$first]);
        $secondCollection = new RouteCollection(routes: [$second]);

        $this->assertSame([$first], $firstCollection->routes);
        $this->assertSame([$second], $secondCollection->routes);
    }

    public function testCreatingRoutesWithSamePathDoesNotThrow(): void
    {
        $path = '/same/';

        $first = $this->createRoute(path: $path);
        $second = $this->createRoute(path: $path);

        $this->assertNotSame($first, $second);
    }
}
