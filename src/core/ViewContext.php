<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

/**
 * What a view factory needs to choose and create the view of the current request.
 */
final readonly class ViewContext
{
    public function __construct(
        public Route $route,
        public ?string $fileGroup,
        public string $fileTitle,
    ) {}
}
