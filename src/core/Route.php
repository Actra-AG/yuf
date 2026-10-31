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

final readonly class Route
{
    public string $viewDirectory;

    /**
     * @param ?Closure(): string $viewCallback Returns the content of the response instead of a view class
     * @param bool $redirectDefaultFileName A request of the default file name (`/home.html`) is redirected permanently
     *     (301) to the path of the route (`/`): one URL per page, no duplicate content. Not for routes with path
     *     variables or `forceFileName`
     * @param bool $usesRequestLanguage The language is resolved per request (`RequestLanguageResolver`: the
     *     `RequestLanguageProvider` of the application, the session, the browser, the first language) instead of the
     *     fixed `language`: one path for all languages, e.g. in a backend. With `isDefaultForLanguage`, the route is
     *     the target of "/" if no route with a fixed language is a default route
     *
     * @throws LogicException for a route with both a fixed language and `usesRequestLanguage`
     */
    public function __construct(
        public string $path,
        string $viewDirectory,
        public ?Closure $viewCallback = null,
        public string $viewClassPrefix = Core::APP_CLASS_PREFIX,
        public string $viewGroup = '',
        public string $defaultFileName = '',
        public bool $isDefaultForLanguage = false,
        public ?ContentType $defaultContentType = null,
        public ?Language $language = null,
        public ?string $acceptedExtension = null,
        public ?string $forceFileGroup = null,
        public ?string $forceFileName = null,
        public ?ViewFactory $viewFactory = null,
        public bool $redirectDefaultFileName = false,
        public bool $usesRequestLanguage = false,
    ) {
        if ($usesRequestLanguage && $language !== null) {
            throw new LogicException(
                message: 'The route "' . $path . '" has a fixed language and usesRequestLanguage: set only one.',
            );
        }
        $this->viewDirectory = $viewDirectory . $viewGroup . '/';
    }

    /**
     * Loads the language files of the view directory for the language of the route, with `usesRequestLanguage` for
     * the language of the locale handler (`global.lang.php` and the file of the requested file title). A route
     * without language has no texts.
     */
    public function loadLocalizedText(string $fileTitle, LocaleHandler $localeHandler): void
    {
        $language = $this->usesRequestLanguage ? $localeHandler->language : $this->language;
        if ($language === null) {
            return;
        }
        $dir = $this->viewDirectory . 'language' . DIRECTORY_SEPARATOR . $language->code . DIRECTORY_SEPARATOR;
        if (!is_dir(filename: $dir)) {
            return;
        }
        $langGlobal = $dir . 'global.lang.php';
        if (file_exists(filename: $langGlobal)) {
            $localeHandler->loadLanguageFile(filePath: $langGlobal, languageDirectory: $dir);
        }
        if ($fileTitle === '') {
            return;
        }
        $langFile = $dir . $fileTitle . '.lang.php';
        if (file_exists(filename: $langFile)) {
            $localeHandler->loadLanguageFile(filePath: $langFile, languageDirectory: $dir);
        }
    }
}
