<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use Exception;
use LogicException;

class LocaleHandler
{
    private static ?LocaleHandler $registeredInstance = null;
    public readonly ?Language $language;
    public private(set) array $loadedLangFiles = [];
    private array $languageBlocks = [];

    /**
     * @throws LogicException if the language is not one of the available languages
     */
    public function __construct(
        ?Language $language,
        LanguageCollection $availableLanguages,
    ) {
        if ($language === null) {
            $this->language = null;
            return;
        }
        $availableLanguage = $availableLanguages->getLanguageByCode(languageCode: $language->code);
        if ($availableLanguage === null) {
            throw new LogicException(message: 'Language ' . $language->code . ' is not available');
        }
        $this->language = $availableLanguage;
    }

    /**
     * The compiled templates (LangTag) read the texts through this accessor until the template refactoring. Views get
     * the instance through ViewContext::$locale.
     */
    public static function get(): LocaleHandler
    {
        return LocaleHandler::$registeredInstance;
    }

    public static function register(LocaleHandler $localeHandler): void
    {
        if (LocaleHandler::$registeredInstance !== null) {
            throw new LogicException(message: 'LocaleHandler is already registered');
        }
        LocaleHandler::$registeredInstance = $localeHandler;
        if ($localeHandler->language === null) {
            return;
        }
        setlocale(category: LC_ALL, locales: $localeHandler->language->locale);
        setlocale(category: LC_NUMERIC, locales: 'en_US');
    }

    public static function isRegistered(): bool
    {
        return LocaleHandler::$registeredInstance !== null;
    }

    public function loadLanguageFile(string $filePath): void
    {
        if (in_array(
            needle: $filePath,
            haystack: $this->loadedLangFiles,
            strict: true,
        )) {
            return;
        }

        if ((int) filesize(filename: $filePath) === 0) {
            return;
        }
        $this->parseLanguageFile(filePath: $filePath);
        $this->loadedLangFiles[] = $filePath;
    }

    private function parseLanguageFile(string $filePath): void
    {
        $txt = [];
        require $filePath;

        foreach ($txt as $key => $val) {
            $this->languageBlocks[$key] = $val;
        }
    }

    public function getText(string $key, array $replacements = []): string
    {
        if (!array_key_exists(key: $key, array: $this->languageBlocks)) {
            throw new Exception(message: 'Missing language fragment for ' . $key);
        }
        $block = $this->languageBlocks[$key];
        if (count(value: $replacements) > 0) {
            $search = [];
            $replace = [];
            foreach ($replacements as $k => $v) {
                $search[] = '[' . strtoupper(string: $k) . ']';
                $replace[] = $v;
            }
            $block = str_ireplace(search: $search, replace: $replace, subject: $block);
        }

        return $block;
    }

    public function getAllText(): array
    {
        return $this->languageBlocks;
    }
}
