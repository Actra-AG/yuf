<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

/**
 * The result of `RequestHandler::resolveRoute()`: the route of the request and everything that depends on it.
 */
final readonly class ResolvedRoute
{
    /**
     * @param ?Language $language The language of the route, else the first available language (`null` without
     *                            languages)
     * @param string $fileName Requested file name, or the default file name of the route if the request has none
     * @param ?string $fileGroup Group of the file: the variable `${fileGroup}` of the route path or the forced one
     * @param string $fileTitle File name without extension and path variables (the first path variable)
     * @param string $fileExtension File extension without the dot, empty if the file name has none
     * @param array<string, string> $routeVariables Variables of the route path (`${name}`) except `fileGroup` and
     *                                              `fileName`
     * @param list<string> $pathVars The parts of the file name between the dashes, the first one is the file title
     */
    public function __construct(
        public Route $route,
        public ?Language $language,
        public string $fileName,
        public ?string $fileGroup,
        public string $fileTitle,
        public string $fileExtension,
        public array $routeVariables,
        public array $pathVars,
    ) {}
}
