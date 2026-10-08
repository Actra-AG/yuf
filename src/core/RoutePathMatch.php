<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

/**
 * The route found for a request path and the variables of its path pattern (`/shop/${fileGroup}/${fileName}`).
 *
 * @internal Used by `RequestHandler` only
 */
final readonly class RoutePathMatch
{
    /**
     * @param array<string, string> $routeVariables
     */
    public function __construct(
        public Route $route,
        public ?string $fileName = null,
        public ?string $fileGroup = null,
        public array $routeVariables = [],
    ) {}
}
