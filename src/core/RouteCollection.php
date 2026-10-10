<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use InvalidArgumentException;
use LogicException;

final class RouteCollection
{
    /** @var list<Route> */
    public private(set) array $routes = [];

    /**
     * @param list<Route> $routes
     * @param ?string $loginPath Local path of the login page (`/login/`, may have a query): a request of a view with
     *     required access rights without a user is redirected there, with the requested URI as return target
     *     (`LoginRedirect`). `null`: the request is answered with the error page 401.
     *
     * @throws InvalidArgumentException if the login path is no local path
     */
    public function __construct(array $routes = [], public readonly ?string $loginPath = null)
    {
        if ($loginPath !== null && !LoginRedirect::isLocalPath(uri: $loginPath)) {
            throw new InvalidArgumentException(message: 'The login path must be a local path starting with "/"');
        }
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
