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
        public readonly string $path,
        public readonly ?Closure $viewCallback = null,
        string $viewDirectory = '{default}',
        public readonly string $viewClassPrefix = Core::APP_CLASS_PREFIX,
        public readonly string $viewGroup = '',
        public readonly string $defaultFileName = '',
        public readonly bool $isDefaultForLanguage = false,
        public readonly ?ContentType $defaultContentType = null,
        public readonly ?Language $language = null,
        public readonly ?string $acceptedExtension = null,
        public readonly ?string $forceFileGroup = null,
        public readonly ?string $forceFileName = null
    ) {
        if (array_key_exists(key: $path, array: Route::$routesByPath)) {
            throw new LogicException(message: 'There is already a route with this path: ' . $path);
        }
        Route::$routesByPath[$path] = $this;
        if ($viewDirectory === '{default}') {
            $this->viewDirectory = Core::get()->viewDirectory . $viewGroup . '/';
        } else {
            $this->viewDirectory = $viewDirectory . $viewGroup . '/';
        }
    }

    public function loadLocalizedText(string $fileTitle): void
    {
        $dir = $this->viewDirectory . 'language' . DIRECTORY_SEPARATOR . $this->language->code . DIRECTORY_SEPARATOR;
        if (!is_dir(filename: $dir)) {
            return;
        }
        $langGlobal = $dir . 'global.lang.php';
        $locale = LocaleHandler::get();
        if (file_exists(filename: $langGlobal)) {
            $locale->loadLanguageFile(filePath: $langGlobal);
        }
        if ($fileTitle === '') {
            return;
        }
        $langFile = $dir . $fileTitle . '.lang.php';
        if (file_exists(filename: $langFile)) {
            $locale->loadLanguageFile(filePath: $langFile);
        }
    }

    public function getPhpClassName(): string
    {
        $phpClassNameParts = [
            $this->viewClassPrefix,
            'view',
            $this->viewGroup,
            'php',
        ];
        $requestHandler = RequestHandler::get();
        if (!is_null(value: $requestHandler->fileGroup)) {
            $phpClassNameParts[] = $requestHandler->fileGroup;
        }
        $phpClassNameParts[] = $requestHandler->fileTitle;

        return implode(
            separator: '\\',
            array: $phpClassNameParts
        );
    }
}