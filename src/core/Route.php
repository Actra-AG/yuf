<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\Core;
use Closure;
use LogicException;

class Route
{
    /** @var Route[] */
    private static array $routesByPath = [];
    public readonly ?string $viewDirectory;

    public function __construct(
        public readonly string       $path,
        public readonly ?Closure     $viewCallback = null,
        public readonly string       $viewGroup = '',
        public readonly string       $defaultFileName = '',
        public readonly bool         $isDefaultForLanguage = false,
        public readonly ?ContentType $defaultContentType = null,
        public readonly ?Language    $language = null,
        public readonly ?string      $acceptedExtension = null,
        public readonly ?string      $forceFileGroup = null,
        public readonly ?string      $forceFileName = null
    )
    {
        if (array_key_exists(key: $path, array: Route::$routesByPath)) {
            throw new LogicException(message: 'There is already a route with this path: ' . $path);
        }
        Route::$routesByPath[$path] = $this;
        $this->viewDirectory = ($viewGroup == '') ? null : Core::get()->viewDirectory . $this->viewGroup . DIRECTORY_SEPARATOR;
    }
}