<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\Language;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\RequestHandler;
use actra\yuf\core\Route;
use actra\yuf\core\RouteCollection;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\session\SessionPreferredLanguage;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * The preferred language of the user: written by `RequestHandler::resolveRoute()` for routes with an explicit
 * language and kept in the data of the session handler (`yuf.handler.preferredLanguage`), so it survives
 * `Session::clearUserData()`.
 *
 * Not covered: the redirect of "/" to the route of the preferred language (`HttpResponse::redirectAndExit()` exits).
 */
final class RequestHandlerPreferredLanguageTest extends TestCase
{
    private Language $german;
    private Language $english;
    private ArraySessionStorage $storage;
    private Session $session;

    #[Override]
    protected function setUp(): void
    {
        $this->german = new Language(code: 'de', locale: 'de_CH.UTF-8');
        $this->english = new Language(code: 'en', locale: 'en_US.UTF-8');
        $this->storage = new ArraySessionStorage();
        $this->session = new Session(storage: $this->storage);
    }

    private function seedPreferredLanguage(string $code): void
    {
        $this->storage->set(key: 'yuf', value: ['handler' => ['preferredLanguage' => $code]]);
    }

    private function storedPreferredLanguage(): ?string
    {
        return new SessionPreferredLanguage(session: $this->session)->getCode();
    }

    private function resolve(
        string $uri,
        ?Language $routeLanguage,
        LanguageCollection $available,
        ?Session $session,
    ): RequestHandler {
        $handler = new RequestHandler(
            httpRequest: HttpRequestFactory::create(uri: $uri),
            routeCollection: new RouteCollection(
                routes: [
                    new Route(
                        path: '/en/',
                        viewDirectory: '/tmp/views/',
                        defaultFileName: 'index.html',
                        isDefaultForLanguage: false,
                        language: $routeLanguage,
                    ),
                ],
            ),
            availableLanguages: $available,
            allowedDomains: ['example.com'],
            session: $session,
        );
        $handler->resolveRoute();

        return $handler;
    }

    private function bothLanguages(): LanguageCollection
    {
        return new LanguageCollection(languages: [$this->german, $this->english]);
    }

    public function testStorageLayoutIsTheKeyPreferredLanguageInTheHandlerSectionWithTheLanguageCode(): void
    {
        $this->resolve(
            uri: '/en/',
            routeLanguage: $this->english,
            available: $this->bothLanguages(),
            session: $this->session,
        );

        $this->assertSame(['yuf' => ['handler' => ['preferredLanguage' => 'en']]], $this->storage->all());
    }

    public function testLanguageOfTheRouteIsRememberedAsPreferredLanguage(): void
    {
        $this->resolve(
            uri: '/en/',
            routeLanguage: $this->english,
            available: $this->bothLanguages(),
            session: $this->session,
        );

        $this->assertSame('en', $this->storedPreferredLanguage());
    }

    public function testPreferredLanguageIsReplacedWhenTheLanguageOfTheRouteDiffers(): void
    {
        $this->seedPreferredLanguage(code: 'de');

        $this->resolve(
            uri: '/en/',
            routeLanguage: $this->english,
            available: $this->bothLanguages(),
            session: $this->session,
        );

        $this->assertSame('en', $this->storedPreferredLanguage());
    }

    public function testPreferredLanguageStaysWhenTheLanguageOfTheRouteIsTheSame(): void
    {
        $this->seedPreferredLanguage(code: 'en');

        $this->resolve(
            uri: '/en/',
            routeLanguage: $this->english,
            available: $this->bothLanguages(),
            session: $this->session,
        );

        $this->assertSame('en', $this->storedPreferredLanguage());
    }

    /**
     * Fix of v4.30.0: before, a route without language wrote the first available language and so overwrote the
     * preferred language of the user.
     */
    public function testRouteWithoutLanguageKeepsThePreferredLanguage(): void
    {
        $this->seedPreferredLanguage(code: 'en');

        $handler = $this->resolve(
            uri: '/en/',
            routeLanguage: null,
            available: $this->bothLanguages(),
            session: $this->session,
        );

        $this->assertSame('en', $this->storedPreferredLanguage());
        $this->assertSame($this->german, $handler->language);
    }

    public function testRouteWithoutLanguageWritesNothing(): void
    {
        $this->resolve(
            uri: '/en/',
            routeLanguage: null,
            available: $this->bothLanguages(),
            session: $this->session,
        );

        $this->assertSame([], $this->storage->all());
    }

    public function testWithoutAvailableLanguagesNothingIsWritten(): void
    {
        $this->resolve(uri: '/en/', routeLanguage: null, available: new LanguageCollection(), session: $this->session);

        $this->assertSame([], $this->storage->all());
    }

    public function testLanguageOfTheRouteThatIsNotAvailableThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The preferred language en is not available');

        $this->resolve(
            uri: '/en/',
            routeLanguage: $this->english,
            available: new LanguageCollection(languages: [$this->german]),
            session: $this->session,
        );
    }

    public function testWithoutSessionNothingIsRemembered(): void
    {
        $handler = $this->resolve(
            uri: '/en/',
            routeLanguage: $this->english,
            available: $this->bothLanguages(),
            session: null,
        );

        $this->assertSame($this->english, $handler->language);
        $this->assertSame([], $this->storage->all());
    }

    public function testPreferredLanguageIsReadFromTheSession(): void
    {
        $this->seedPreferredLanguage(code: 'en');

        $this->assertSame('en', $this->storedPreferredLanguage());
    }

    public function testWithoutPreferredLanguageTheCodeIsNull(): void
    {
        $this->assertNull($this->storedPreferredLanguage());
    }

    public function testPreferredLanguageThatIsNoStringIsIgnored(): void
    {
        $this->storage->set(key: 'yuf', value: ['handler' => ['preferredLanguage' => ['en']]]);

        $this->assertNull($this->storedPreferredLanguage());
    }

    public function testSettingThePreferredLanguageKeepsTheOtherHandlerData(): void
    {
        $this->storage->set(key: 'yuf', value: ['handler' => ['sessionCreated' => 1_790_000_000]]);

        new SessionPreferredLanguage(session: $this->session)->set(language: $this->english);

        $this->assertSame(
            ['yuf' => ['handler' => ['sessionCreated' => 1_790_000_000, 'preferredLanguage' => 'en']]],
            $this->storage->all(),
        );
    }

    public function testPreferredLanguageSurvivesTheClearingOfTheUserData(): void
    {
        $this->seedPreferredLanguage(code: 'en');
        $this->session->set(key: 'other', value: 'data');

        $this->session->clearUserData();

        $this->assertSame('en', $this->storedPreferredLanguage());
        $this->assertFalse($this->session->has(key: 'other'));
    }
}
