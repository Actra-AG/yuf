<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use LogicException;

final class RouteCollection
{
    /** @var list<Route> */
    public private(set) array $routes = [];

    /**
     * @param list<Route> $routes
     * @param ?LoginTarget $loginTarget The login page (`LoginTarget::local(path: '/login/')` or
     *     `LoginTarget::remote(url: 'https://login.example.net/login/')`): a request of a view with required access
     *     rights without a user is redirected there, with the requested page as return target (`LoginRedirect`).
     *     `null`: the request is answered with the error page 401.
     */
    public function __construct(array $routes = [], public readonly ?LoginTarget $loginTarget = null)
    {
        foreach ($routes as $item) {
            $this->addRoute(route: $item);
        }
    }

    public function addRoute(Route $route): void
    {
        if (array_any(
            array: $this->routes,
            callback: fn(Route $existingRoute): bool => $existingRoute->path === $route->path,
        )) {
            throw new LogicException(message: 'There is already a route with this path: ' . $route->path);
        }
        $this->routes[] = $route;
    }

    public function hasRoutes(): bool
    {
        return $this->routes !== [];
    }

    public function getRouteForLanguage(string $languageCode): ?Route
    {
        return array_find(
            array: $this->routes,
            callback: fn(Route $route): bool => $route->language?->code === $languageCode,
        );
    }

    /**
     * @throws LogicException if the collection has no routes (check `hasRoutes()` first)
     */
    public function getFirstRoute(): Route
    {
        return array_first(array: $this->routes)
            ?? throw new LogicException(message: 'The route collection is empty, there is no first route');
    }
}
