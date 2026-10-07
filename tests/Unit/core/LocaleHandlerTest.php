<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\Language;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LocaleHandler;
use Exception;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Not covered: register() (it calls setlocale() for the whole process).
 */
final class LocaleHandlerTest extends TestCase
{
    private const string TEXT_FILE = __DIR__ . '/../../Fixture/localeTexts.lang.php';
    private const string EMPTY_FILE = __DIR__ . '/../../Fixture/localeEmpty.lang.php';

    private function createHandler(): LocaleHandler
    {
        return new LocaleHandler(language: null, availableLanguages: new LanguageCollection());
    }

    public function testConstructorKeepsAvailableLanguage(): void
    {
        $english = new Language(code: 'en', locale: 'en_US.UTF-8');

        $handler = new LocaleHandler(
            language: $english,
            availableLanguages: new LanguageCollection(languages: [$english]),
        );

        $this->assertSame($english, $handler->language);
    }

    public function testConstructorAcceptsNullLanguage(): void
    {
        $this->assertNull($this->createHandler()->language);
    }

    public function testConstructorRejectsUnavailableLanguage(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('Language fr is not available');

        new LocaleHandler(
            language: new Language(code: 'fr', locale: 'fr_FR.UTF-8'),
            availableLanguages: new LanguageCollection(languages: [new Language(code: 'en', locale: 'en_US.UTF-8')]),
        );
    }

    public function testLoadLanguageFileMakesTextsAvailable(): void
    {
        $handler = $this->createHandler();

        $handler->loadLanguageFile(filePath: LocaleHandlerTest::TEXT_FILE);

        $this->assertSame('Plain text', $handler->getText(key: 'plain'));
        $this->assertSame([LocaleHandlerTest::TEXT_FILE], $handler->loadedLangFiles);
    }

    public function testLoadLanguageFileSkipsEmptyFile(): void
    {
        $handler = $this->createHandler();

        $handler->loadLanguageFile(filePath: LocaleHandlerTest::EMPTY_FILE);

        $this->assertSame([], $handler->loadedLangFiles);
        $this->assertSame([], $handler->getAllText());
    }

    public function testLoadLanguageFileLoadsSameFileOnce(): void
    {
        $handler = $this->createHandler();

        $handler->loadLanguageFile(filePath: LocaleHandlerTest::TEXT_FILE);
        $handler->loadLanguageFile(filePath: LocaleHandlerTest::TEXT_FILE);

        $this->assertSame([LocaleHandlerTest::TEXT_FILE], $handler->loadedLangFiles);
    }

    public function testGetTextReplacesPlaceholdersCaseInsensitive(): void
    {
        $handler = $this->createHandler();
        $handler->loadLanguageFile(filePath: LocaleHandlerTest::TEXT_FILE);

        $text = $handler->getText(key: 'greeting', replacements: ['name' => 'Anna', 'PLACE' => 'yuf']);

        $this->assertSame('Hello Anna, welcome to yuf', $text);
    }

    public function testGetTextThrowsForMissingKey(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageIs('Missing language fragment for missing');

        $this->createHandler()->getText(key: 'missing');
    }

    public function testGetAllTextReturnsAllLoadedTexts(): void
    {
        $handler = $this->createHandler();
        $handler->loadLanguageFile(filePath: LocaleHandlerTest::TEXT_FILE);

        $this->assertSame(
            ['greeting' => 'Hello [NAME], welcome to [place]', 'plain' => 'Plain text'],
            $handler->getAllText(),
        );
    }
}
