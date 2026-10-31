<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\Language;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\RequestLanguageProvider;
use actra\yuf\core\RequestLanguageResolver;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\session\SessionStorage;
use actra\yuf\tests\Double\core\FixedRequestLanguageProvider;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\session\CountingSessionStorage;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * The resolution order: provider of the application, language of the session, browser language, first language.
 */
final class RequestLanguageResolverTest extends TestCase
{
    private Language $german;
    private Language $english;
    private Language $french;

    #[Override]
    protected function setUp(): void
    {
        $this->german = new Language(code: 'de', locale: 'de_CH.UTF-8');
        $this->english = new Language(code: 'en', locale: 'en_US.UTF-8');
        $this->french = new Language(code: 'fr', locale: 'fr_CH.UTF-8');
    }

    private function createSession(?string $preferredLanguageCode, SessionStorage $storage = new ArraySessionStorage()): Session
    {
        if ($preferredLanguageCode !== null) {
            $storage->set(key: 'yuf', value: ['handler' => ['preferredLanguage' => $preferredLanguageCode]]);
        }

        return new Session(storage: $storage);
    }

    private function resolve(
        ?RequestLanguageProvider $provider = null,
        ?Session $session = null,
        string $acceptLanguage = '',
        ?LanguageCollection $availableLanguages = null,
    ): ?Language {
        return new RequestLanguageResolver(
            httpRequest: HttpRequestFactory::create(
                headers: $acceptLanguage === '' ? [] : ['Accept-Language' => $acceptLanguage],
            ),
            availableLanguages: $availableLanguages
                ?? new LanguageCollection(languages: [$this->german, $this->english, $this->french]),
            session: $session,
            requestLanguageProvider: $provider,
        )->resolve();
    }

    public function testLanguageOfTheProviderComesFirst(): void
    {
        $this->assertSame($this->french, $this->resolve(
            provider: new FixedRequestLanguageProvider(languageCode: 'fr'),
            session: $this->createSession(preferredLanguageCode: 'en'),
            acceptLanguage: 'de',
        ));
    }

    public function testProviderGetsTheSessionOfTheRequest(): void
    {
        $provider = new FixedRequestLanguageProvider(languageCode: 'fr');
        $session = $this->createSession(preferredLanguageCode: null);

        $this->resolve(provider: $provider, session: $session);

        $this->assertSame(1, $provider->calls);
        $this->assertSame($session, $provider->receivedSession);
    }

    public function testLanguageOfTheSessionIsUsedIfTheProviderDecidesNothing(): void
    {
        $this->assertSame($this->english, $this->resolve(
            provider: new FixedRequestLanguageProvider(languageCode: null),
            session: $this->createSession(preferredLanguageCode: 'en'),
            acceptLanguage: 'fr',
        ));
    }

    public function testUnavailableLanguageOfTheProviderIsIgnored(): void
    {
        $this->assertSame($this->english, $this->resolve(
            provider: new FixedRequestLanguageProvider(languageCode: 'it'),
            session: $this->createSession(preferredLanguageCode: 'en'),
        ));
    }

    public function testLanguageOfTheSessionIsUsedWithoutProvider(): void
    {
        $this->assertSame($this->english, $this->resolve(
            session: $this->createSession(preferredLanguageCode: 'en'),
            acceptLanguage: 'fr',
        ));
    }

    public function testInactiveSessionIsNotRead(): void
    {
        $storage = new CountingSessionStorage(active: false);
        $session = $this->createSession(preferredLanguageCode: 'en', storage: $storage);
        $storage->accesses = 0;

        $language = $this->resolve(session: $session, acceptLanguage: 'fr');

        $this->assertSame($this->french, $language);
        $this->assertSame(0, $storage->accesses);
    }

    public function testBrowserLanguageIsUsedIfTheSessionHasNoLanguage(): void
    {
        $this->assertSame($this->french, $this->resolve(
            session: $this->createSession(preferredLanguageCode: null),
            acceptLanguage: 'fr-CH, en;q=0.8',
        ));
    }

    public function testUnavailableLanguageOfTheSessionFallsBackToTheBrowserLanguage(): void
    {
        $this->assertSame($this->english, $this->resolve(
            session: $this->createSession(preferredLanguageCode: 'it'),
            acceptLanguage: 'en',
        ));
    }

    public function testFirstAvailableBrowserLanguageByQualityWins(): void
    {
        $this->assertSame($this->english, $this->resolve(acceptLanguage: 'it, fr;q=0.5, en;q=0.9'));
    }

    public function testFirstLanguageIsUsedWithoutAnyMatch(): void
    {
        $this->assertSame($this->german, $this->resolve(
            provider: new FixedRequestLanguageProvider(languageCode: null),
            session: $this->createSession(preferredLanguageCode: null),
            acceptLanguage: 'it',
        ));
    }

    public function testNoLanguageWithoutAvailableLanguagesAndTheProviderIsNotAsked(): void
    {
        $provider = new FixedRequestLanguageProvider(languageCode: 'en');

        $this->assertNull($this->resolve(provider: $provider, availableLanguages: new LanguageCollection()));
        $this->assertSame(0, $provider->calls);
    }
}
