<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use LogicException;

class RouteCollection
{
    /**
     * @var Route[]
     */
    public private(set) array $routes = [];

    public function __construct(array $routes = [])
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
        return (count(value: $this->routes) > 0);
    }

    public function getRouteForLanguage(string $languageCode): ?Route
    {
        return array_find($this->routes, fn($route) => $route->language->code === $languageCode);
    }

    public function getFirstRoute(): Route
    {
        return current(array: $this->routes);
    }
}
