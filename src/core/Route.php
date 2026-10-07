<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\Core;
use Closure;

class Route
{
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
        public readonly ?string $forceFileName = null,
        public readonly ?ViewFactory $viewFactory = null,
    ) {
        if ($viewDirectory === '{default}') {
            $this->viewDirectory = Core::get()->viewDirectory . $viewGroup . '/';
        } else {
            $this->viewDirectory = $viewDirectory . $viewGroup . '/';
        }
    }

    public function loadLocalizedText(string $fileTitle, LocaleHandler $localeHandler): void
    {
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
