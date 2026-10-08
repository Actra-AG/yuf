<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use LogicException;

final class LanguageCollection
{
    /** @var list<Language> */
    public private(set) array $languages = [];

    /**
     * @param list<Language> $languages
     */
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
        return $this->getLanguageByCode(languageCode: $languageCode) !== null;
    }

    public function getLanguageByCode(string $languageCode): ?Language
    {
        return array_find(
            array: $this->languages,
            callback: fn(Language $language): bool => $language->code === $languageCode,
        );
    }

    /**
     * @throws LogicException if the collection is empty (check `isEmpty()` first)
     */
    public function getFirstLanguage(): Language
    {
        return array_first(array: $this->languages)
            ?? throw new LogicException(message: 'The language collection is empty, there is no first language');
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
