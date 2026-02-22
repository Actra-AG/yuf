<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

class LanguageCollection
{
    /** @var Language[] */
    private(set) array $languages = [];

    public function __construct(array $languages = [])
    {
        foreach ($languages as $language) {
            $this->add(language: $language);
        }
    }

    public function add(Language $language): void
    {
        $this->languages[] = $language;
    }

    public function hasLanguage(string $languageCode): bool
    {
        return !is_null(value: $this->getLanguageByCode(languageCode: $languageCode));
    }

    public function getLanguageByCode(string $languageCode): ?Language
    {
        return array_find($this->languages, fn($language) => $language->code === $languageCode);
    }

    public function getFirstLanguage(): Language
    {
        return current(array: $this->languages);
    }

    public function isMultiLang(): bool
    {
        return count(value: $this->languages) > 1;
    }

    public function isEmpty(): bool
    {
        return $this->languages === [];
    }
}