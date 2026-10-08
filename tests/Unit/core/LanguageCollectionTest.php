<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\Language;
use actra\yuf\core\LanguageCollection;
use LogicException;
use PHPUnit\Framework\TestCase;

final class LanguageCollectionTest extends TestCase
{
    public function testEmptyCollection(): void
    {
        $collection = new LanguageCollection();

        $this->assertTrue($collection->isEmpty());
        $this->assertFalse($collection->isMultiLang());
        $this->assertFalse($collection->hasLanguage(languageCode: 'de'));
        $this->assertNull($collection->getLanguageByCode(languageCode: 'de'));
    }

    public function testFirstLanguageOfAnEmptyCollectionThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The language collection is empty, there is no first language');

        new LanguageCollection()->getFirstLanguage();
    }

    public function testLanguagesKeepTheirOrder(): void
    {
        $german = new Language(code: 'de', locale: 'de_CH.UTF-8');
        $english = new Language(code: 'en', locale: 'en_US.UTF-8');
        $collection = new LanguageCollection(languages: [$german]);

        $collection->add(language: $english);

        $this->assertSame([$german, $english], $collection->languages);
        $this->assertSame($german, $collection->getFirstLanguage());
        $this->assertTrue($collection->isMultiLang());
        $this->assertSame($english, $collection->getLanguageByCode(languageCode: 'en'));
    }

    public function testSingleLanguageIsNotMultiLang(): void
    {
        $collection = new LanguageCollection(languages: [new Language(code: 'de', locale: 'de_CH.UTF-8')]);

        $this->assertFalse($collection->isMultiLang());
        $this->assertFalse($collection->isEmpty());
    }
}
