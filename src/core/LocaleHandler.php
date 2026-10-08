<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use LogicException;
use OutOfBoundsException;
use RuntimeException;
use UnexpectedValueException;

final class LocaleHandler
{
    public readonly ?Language $language;
    /** @var list<string> */
    public private(set) array $loadedLangFiles = [];
    /** @var array<string, string> */
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
     * Sets the system locale of the process to the locale of the language (numbers always use `en_US`). Does nothing
     * for a handler without language.
     */
    public function applySystemLocale(): void
    {
        if ($this->language === null) {
            return;
        }
        setlocale(category: LC_ALL, locales: $this->language->locale);
        setlocale(category: LC_NUMERIC, locales: 'en_US');
    }

    /**
     * Loads the texts of a language file (`$txt['key'] = 'text';`) once; an empty file is ignored.
     *
     * @throws RuntimeException if the file size cannot be read
     * @throws UnexpectedValueException if the file defines a text that is not a string
     */
    public function loadLanguageFile(string $filePath): void
    {
        if (in_array(
            needle: $filePath,
            haystack: $this->loadedLangFiles,
            strict: true,
        )) {
            return;
        }
        $fileSize = filesize(filename: $filePath);
        if ($fileSize === false) {
            throw new RuntimeException(message: 'Cannot read the size of the language file ' . $filePath);
        }
        if ($fileSize === 0) {
            return;
        }
        $this->parseLanguageFile(filePath: $filePath);
        $this->loadedLangFiles[] = $filePath;
    }

    private function parseLanguageFile(string $filePath): void
    {
        /** @var array<array-key, mixed> $txt */
        $txt = [];
        require $filePath;

        foreach ($txt as $key => $text) {
            if (!is_string(value: $text)) {
                throw new UnexpectedValueException(
                    message: 'The text "' . $key . '" of the language file ' . $filePath . ' must be a string, '
                        . get_debug_type(value: $text) . ' given.',
                );
            }
            $this->languageBlocks[(string) $key] = $text;
        }
    }

    /**
     * @param array<string, string> $replacements Value by placeholder name: `[NAME]` in the text (case-insensitive)
     *
     * @throws OutOfBoundsException if the file groups loaded so far have no text for the key
     */
    public function getText(string $key, array $replacements = []): string
    {
        if (!array_key_exists(key: $key, array: $this->languageBlocks)) {
            throw new OutOfBoundsException(message: 'Missing language fragment for ' . $key);
        }
        $block = $this->languageBlocks[$key];
        if ($replacements === []) {
            return $block;
        }
        $search = [];
        $replace = [];
        foreach ($replacements as $name => $value) {
            $search[] = '[' . strtoupper(string: $name) . ']';
            $replace[] = $value;
        }

        return str_ireplace(search: $search, replace: $replace, subject: $block);
    }

    /**
     * @return array<string, string>
     */
    public function getAllText(): array
    {
        return $this->languageBlocks;
    }
}
