<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\Language;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LanguageSwitch;
use actra\yuf\core\RequestMethodEnum;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\session\SessionPreferredLanguage;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use Override;
use PHPUnit\Framework\TestCase;

final class LanguageSwitchTest extends TestCase
{
    private Session $session;

    #[Override]
    protected function setUp(): void
    {
        $this->session = new Session(storage: new ArraySessionStorage());
    }

    /**
     * @param array<string, string> $queryParameters
     * @param array<string, string> $postParameters
     */
    private function apply(array $queryParameters = [], array $postParameters = []): string
    {
        return new LanguageSwitch(
            httpRequest: HttpRequestFactory::create(
                method: $postParameters === [] ? RequestMethodEnum::GET : RequestMethodEnum::POST,
                queryParameters: $queryParameters,
                postParameters: $postParameters,
            ),
            session: $this->session,
            availableLanguages: new LanguageCollection(languages: [
                new Language(code: 'de', locale: 'de_CH.UTF-8'),
                new Language(code: 'en', locale: 'en_US.UTF-8'),
            ]),
        )->apply(defaultPath: '/home/');
    }

    private function storedLanguageCode(): ?string
    {
        return new SessionPreferredLanguage(session: $this->session)->getCode();
    }

    public function testLinkSetsTheLanguageAndReturnsToThePage(): void
    {
        $returnPath = $this->apply(queryParameters: ['language' => 'en', 'returnTo' => '/domains/?page=2']);

        $this->assertSame('/domains/?page=2', $returnPath);
        $this->assertSame('en', $this->storedLanguageCode());
    }

    public function testFormSetsTheLanguage(): void
    {
        $returnPath = $this->apply(postParameters: ['language' => 'en', 'returnTo' => '/domains/']);

        $this->assertSame('/domains/', $returnPath);
        $this->assertSame('en', $this->storedLanguageCode());
    }

    public function testUnavailableLanguageChangesNothing(): void
    {
        $this->apply(queryParameters: ['language' => 'fr']);

        $this->assertNull($this->storedLanguageCode());
    }

    public function testReturnTargetThatIsNoLocalPathLeadsToTheDefaultPath(): void
    {
        $this->assertSame('/home/', $this->apply(queryParameters: ['language' => 'en', 'returnTo' => '//evil.example']));
        $this->assertSame('/home/', $this->apply(queryParameters: ['returnTo' => 'https://evil.example/']));
    }

    public function testWithoutReturnTargetLeadsToTheDefaultPath(): void
    {
        $this->assertSame('/home/', $this->apply(queryParameters: ['language' => 'en']));
    }

    public function testCreateUriAddsTheLanguageAndTheLocalReturnTarget(): void
    {
        $this->assertSame(
            '/language/?language=en&returnTo=%2Fdomains%2F%3Fpage%3D2',
            LanguageSwitch::createUri(switchPath: '/language/', languageCode: 'en', returnUri: '/domains/?page=2'),
        );
        $this->assertSame(
            '/language/?language=de',
            LanguageSwitch::createUri(switchPath: '/language/', languageCode: 'de', returnUri: '//evil.example'),
        );
    }
}
