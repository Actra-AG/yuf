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
use actra\yuf\core\Route;
use Override;
use PHPUnit\Framework\TestCase;

final class RouteTest extends TestCase
{
    private string $viewDirectory;

    #[Override]
    protected function setUp(): void
    {
        $this->viewDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-route-'
            . bin2hex(string: random_bytes(length: 8)) . DIRECTORY_SEPARATOR;
        $languageDirectory = $this->viewDirectory . 'frontend/language/en/';
        mkdir(directory: $languageDirectory, recursive: true);
        file_put_contents(filename: $languageDirectory . 'global.lang.php', data: "<?php\n\$txt = ['global' => 'G'];\n");
        file_put_contents(filename: $languageDirectory . 'detail.lang.php', data: "<?php\n\$txt = ['detail' => 'D'];\n");
    }

    #[Override]
    protected function tearDown(): void
    {
        $files = glob(pattern: $this->viewDirectory . 'frontend/language/en/*');
        foreach ($files === false ? [] : $files as $file) {
            unlink(filename: $file);
        }
        rmdir(directory: $this->viewDirectory . 'frontend/language/en');
        rmdir(directory: $this->viewDirectory . 'frontend/language');
        rmdir(directory: $this->viewDirectory . 'frontend');
        rmdir(directory: $this->viewDirectory);
    }

    private function createLocaleHandler(Language $language): LocaleHandler
    {
        return new LocaleHandler(
            language: $language,
            availableLanguages: new LanguageCollection(languages: [$language]),
        );
    }

    public function testViewDirectoryIsExtendedByViewGroup(): void
    {
        $route = new Route(path: '/', viewDirectory: '/app/view/', viewGroup: 'frontend');

        $this->assertSame('/app/view/frontend/', $route->viewDirectory);
    }

    public function testViewDirectoryWithoutViewGroupEndsWithSlash(): void
    {
        $route = new Route(path: '/', viewDirectory: '/app/view/');

        $this->assertSame('/app/view//', $route->viewDirectory);
    }

    public function testLoadLocalizedTextLoadsTheGlobalAndTheFileTexts(): void
    {
        $english = new Language(code: 'en', locale: 'en_US.UTF-8');
        $route = new Route(path: '/', viewDirectory: $this->viewDirectory, viewGroup: 'frontend', language: $english);
        $localeHandler = $this->createLocaleHandler(language: $english);

        $route->loadLocalizedText(fileTitle: 'detail', localeHandler: $localeHandler);

        $this->assertSame(['global' => 'G', 'detail' => 'D'], $localeHandler->getAllText());
    }

    public function testLoadLocalizedTextWithoutFileTitleLoadsOnlyTheGlobalTexts(): void
    {
        $english = new Language(code: 'en', locale: 'en_US.UTF-8');
        $route = new Route(path: '/', viewDirectory: $this->viewDirectory, viewGroup: 'frontend', language: $english);
        $localeHandler = $this->createLocaleHandler(language: $english);

        $route->loadLocalizedText(fileTitle: '', localeHandler: $localeHandler);

        $this->assertSame(['global' => 'G'], $localeHandler->getAllText());
    }

    public function testLoadLocalizedTextIgnoresAMissingFile(): void
    {
        $english = new Language(code: 'en', locale: 'en_US.UTF-8');
        $route = new Route(path: '/', viewDirectory: $this->viewDirectory, viewGroup: 'frontend', language: $english);
        $localeHandler = $this->createLocaleHandler(language: $english);

        $route->loadLocalizedText(fileTitle: 'unknown', localeHandler: $localeHandler);

        $this->assertSame(['global' => 'G'], $localeHandler->getAllText());
    }

    public function testLoadLocalizedTextIgnoresALanguageWithoutDirectory(): void
    {
        $german = new Language(code: 'de', locale: 'de_CH.UTF-8');
        $route = new Route(path: '/', viewDirectory: $this->viewDirectory, viewGroup: 'frontend', language: $german);
        $localeHandler = $this->createLocaleHandler(language: $german);

        $route->loadLocalizedText(fileTitle: 'detail', localeHandler: $localeHandler);

        $this->assertSame([], $localeHandler->getAllText());
    }

    public function testRouteWithoutLanguageHasNoTexts(): void
    {
        $route = new Route(path: '/', viewDirectory: $this->viewDirectory, viewGroup: 'frontend');
        $localeHandler = new LocaleHandler(language: null, availableLanguages: new LanguageCollection());

        $route->loadLocalizedText(fileTitle: 'detail', localeHandler: $localeHandler);

        $this->assertSame([], $localeHandler->getAllText());
    }
}
