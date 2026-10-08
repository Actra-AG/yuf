<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\Core;
use Closure;

final readonly class Route
{
    public string $viewDirectory;

    /**
     * @param ?Closure(): string $viewCallback Returns the content of the response instead of a view class
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
    ) {
        $this->viewDirectory = $viewDirectory . $viewGroup . '/';
    }

    /**
     * Loads the language files of the view directory for the language of the route (`global.lang.php` and the file of
     * the requested file title). A route without language has no texts.
     */
    public function loadLocalizedText(string $fileTitle, LocaleHandler $localeHandler): void
    {
        if ($this->language === null) {
            return;
        }
        $dir = $this->viewDirectory . 'language' . DIRECTORY_SEPARATOR . $this->language->code . DIRECTORY_SEPARATOR;
        if (!is_dir(filename: $dir)) {
            return;
        }
        $langGlobal = $dir . 'global.lang.php';
        if (file_exists(filename: $langGlobal)) {
            $localeHandler->loadLanguageFile(filePath: $langGlobal);
        }
        if ($fileTitle === '') {
            return;
        }
        $langFile = $dir . $fileTitle . '.lang.php';
        if (file_exists(filename: $langFile)) {
            $localeHandler->loadLanguageFile(filePath: $langFile);
        }
    }
}
