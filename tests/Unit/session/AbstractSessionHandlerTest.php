<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\session;

use actra\yuf\auth\AuthSession;
use actra\yuf\clock\FixedClock;
use actra\yuf\session\FileSessionHandler;
use actra\yuf\session\NativeSessionStorage;
use actra\yuf\session\Session;
use actra\yuf\session\SessionSettings;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\session\OutputSentSessionHandler;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Session handler: the data of a started session (`yuf.handler`) and the protection against session fixation. What
 * `Session::clearUserData()` keeps is tested in `SessionTest`.
 */
final class AbstractSessionHandlerTest extends TestCase
{
    private const string SESSION_NAME = 'yufTestSession';
    private const string COOKIE_SESSION_ID = '0123456789abcdef0123456789abcdef';
    private const string REQUESTED_SESSION_ID = 'fedcba9876543210fedcba9876543210';

    /**
     * Regression test for session fixation: a session ID in the URL or the POST data must not replace the session ID
     * of the cookie, even if a session with that ID exists.
     */
    #[RunInSeparateProcess]
    public function testSessionIdIsNotReadFromRequestInput(): void
    {
        $savePath = $this->createSessionSavePath();
        // session_start() reads the session ID from $_COOKIE itself, the request has the same cookie
        $_COOKIE[AbstractSessionHandlerTest::SESSION_NAME] = AbstractSessionHandlerTest::COOKIE_SESSION_ID;
        $httpRequest = HttpRequestFactory::create(
            cookies: [AbstractSessionHandlerTest::SESSION_NAME => AbstractSessionHandlerTest::COOKIE_SESSION_ID],
            queryParameters: [AbstractSessionHandlerTest::SESSION_NAME => AbstractSessionHandlerTest::REQUESTED_SESSION_ID],
            postParameters: [AbstractSessionHandlerTest::SESSION_NAME => AbstractSessionHandlerTest::REQUESTED_SESSION_ID],
        );

        try {
            $sessionHandler = new FileSessionHandler(httpRequest: $httpRequest, sessionSettings: new SessionSettings(
                savePath: $savePath,
                individualName: AbstractSessionHandlerTest::SESSION_NAME,
            ), defaultSavePath: '/not/used');
            $sessionId = $sessionHandler->getId();
            session_write_close();
        } finally {
            $this->removeSessionSavePath(savePath: $savePath);
        }

        $this->assertSame(AbstractSessionHandlerTest::COOKIE_SESSION_ID, $sessionId);
        $this->assertSame(
            [AbstractSessionHandlerTest::SESSION_NAME => AbstractSessionHandlerTest::COOKIE_SESSION_ID],
            $_COOKIE,
        );
    }

    /**
     * The layout of the data of the session handler in a new session: four keys in `yuf.handler`.
     */
    #[RunInSeparateProcess]
    public function testNewSessionStartsWithTheDataOfTheSessionHandler(): void
    {
        $savePath = $this->createSessionSavePath();
        $_COOKIE[AbstractSessionHandlerTest::SESSION_NAME] = AbstractSessionHandlerTest::COOKIE_SESSION_ID;
        $httpRequest = HttpRequestFactory::create(
            headers: ['User-Agent' => 'Browser'],
            cookies: [AbstractSessionHandlerTest::SESSION_NAME => AbstractSessionHandlerTest::COOKIE_SESSION_ID],
        );

        try {
            $sessionHandler = new FileSessionHandler(
                httpRequest: $httpRequest,
                sessionSettings: new SessionSettings(
                    savePath: $savePath,
                    individualName: AbstractSessionHandlerTest::SESSION_NAME,
                ),
                defaultSavePath: '/not/used',
                clock: new FixedClock(now: new DateTimeImmutable(datetime: '@1790000000')),
            );
            $sessionHandler->ensureStarted();
            $session = $_SESSION;
            session_write_close();
        } finally {
            $this->removeSessionSavePath(savePath: $savePath);
        }

        $this->assertSame(
            [
                'yuf' => [
                    'handler' => [
                        'sessionCreated' => 1_790_000_000,
                        'trustedRemoteAddress' => '192.0.2.1',
                        'trustedUserAgent' => 'Browser',
                        'lastActivity' => 1_790_000_000,
                    ],
                ],
            ],
            $session,
        );
    }

    /**
     * A session of the same client (address and user agent) keeps its data, only `lastActivity` is updated.
     */
    #[RunInSeparateProcess]
    public function testExistingSessionOfTheSameClientKeepsItsData(): void
    {
        $savePath = $this->createSessionSavePath();
        file_put_contents(
            filename: $savePath . DIRECTORY_SEPARATOR . 'sess_' . AbstractSessionHandlerTest::COOKIE_SESSION_ID,
            data: AbstractSessionHandlerTest::serialized(data: [
                'yuf' => [
                    'handler' => [
                        'sessionCreated' => 1_790_000_000,
                        'trustedRemoteAddress' => '192.0.2.1',
                        'trustedUserAgent' => 'Browser',
                        'lastActivity' => 1_790_000_100,
                        'preferredLanguage' => 'de',
                    ],
                    'tables' => ['items' => []],
                ],
            ]),
        );
        $_COOKIE[AbstractSessionHandlerTest::SESSION_NAME] = AbstractSessionHandlerTest::COOKIE_SESSION_ID;
        $httpRequest = HttpRequestFactory::create(
            headers: ['User-Agent' => 'Browser'],
            cookies: [AbstractSessionHandlerTest::SESSION_NAME => AbstractSessionHandlerTest::COOKIE_SESSION_ID],
        );

        try {
            $sessionHandler = new FileSessionHandler(
                httpRequest: $httpRequest,
                sessionSettings: new SessionSettings(
                    savePath: $savePath,
                    individualName: AbstractSessionHandlerTest::SESSION_NAME,
                ),
                defaultSavePath: '/not/used',
                clock: new FixedClock(now: new DateTimeImmutable(datetime: '@1790000200')),
            );
            $sessionHandler->ensureStarted();
            $session = $_SESSION;
            session_write_close();
        } finally {
            $this->removeSessionSavePath(savePath: $savePath);
        }

        $this->assertSame(
            [
                'yuf' => [
                    'handler' => [
                        'sessionCreated' => 1_790_000_000,
                        'trustedRemoteAddress' => '192.0.2.1',
                        'trustedUserAgent' => 'Browser',
                        'lastActivity' => 1_790_000_200,
                        'preferredLanguage' => 'de',
                    ],
                    'tables' => ['items' => []],
                ],
            ],
            $session,
        );
    }

    /**
     * A session of yuf before v4.30.0 (top-level keys) is a new session for the handler: the login state and all other
     * data of the old layout are not read, so the user has to log in again.
     */
    #[RunInSeparateProcess]
    public function testSessionOfTheOldLayoutIsNotLoggedIn(): void
    {
        $savePath = $this->createSessionSavePath();
        file_put_contents(
            filename: $savePath . DIRECTORY_SEPARATOR . 'sess_' . AbstractSessionHandlerTest::COOKIE_SESSION_ID,
            data: 'sessionCreated|i:1790000000;trustedRemoteAddress|s:9:"192.0.2.1";trustedUserAgent|s:7:"Browser";'
            . 'lastActivity|i:1790000100;auth_userSession|a:2:{s:10:"isLoggedIn";b:1;s:13:"authSessionId";i:5;}',
        );
        $_COOKIE[AbstractSessionHandlerTest::SESSION_NAME] = AbstractSessionHandlerTest::COOKIE_SESSION_ID;
        $httpRequest = HttpRequestFactory::create(
            headers: ['User-Agent' => 'Browser'],
            cookies: [AbstractSessionHandlerTest::SESSION_NAME => AbstractSessionHandlerTest::COOKIE_SESSION_ID],
        );

        try {
            $sessionHandler = new FileSessionHandler(
                httpRequest: $httpRequest,
                sessionSettings: new SessionSettings(
                    savePath: $savePath,
                    individualName: AbstractSessionHandlerTest::SESSION_NAME,
                ),
                defaultSavePath: '/not/used',
                clock: new FixedClock(now: new DateTimeImmutable(datetime: '@1790000200')),
            );
            $session = new Session(storage: new NativeSessionStorage(sessionHandler: $sessionHandler));
            $isLoggedIn = new AuthSession(session: $session)->isLoggedIn();
            $sessionCreated = $sessionHandler->getSessionCreated();
            session_write_close();
        } finally {
            $this->removeSessionSavePath(savePath: $savePath);
        }

        $this->assertFalse($isLoggedIn);
        $this->assertSame(1_790_000_200, $sessionCreated);
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function untrustedClientProvider(): iterable
    {
        yield 'other remote address' => ['192.0.2.99', 'Browser', 1_790_000_200];
        yield 'other user agent' => ['192.0.2.1', 'Other Browser', 1_790_000_200];
        yield 'expired session' => ['192.0.2.1', 'Browser', 1_790_000_000 + 3601 + 100];
    }

    /**
     * A session that is used from another address or user agent, or that is expired, is replaced: no data of the
     * old session is kept, the session ID changes and the handler data describes the new client.
     */
    #[DataProvider('untrustedClientProvider')]
    #[RunInSeparateProcess]
    public function testSessionOfAnUntrustedClientOrAnExpiredSessionIsReplaced(
        string $remoteAddress,
        string $userAgent,
        int $now,
    ): void {
        $savePath = $this->createSessionSavePath();
        $this->writeExistingSession(savePath: $savePath, lastActivity: 1_790_000_100);
        $_COOKIE[AbstractSessionHandlerTest::SESSION_NAME] = AbstractSessionHandlerTest::COOKIE_SESSION_ID;
        $httpRequest = HttpRequestFactory::create(
            remoteAddress: $remoteAddress,
            headers: ['User-Agent' => $userAgent],
            cookies: [AbstractSessionHandlerTest::SESSION_NAME => AbstractSessionHandlerTest::COOKIE_SESSION_ID],
        );

        try {
            $sessionHandler = new FileSessionHandler(
                httpRequest: $httpRequest,
                sessionSettings: new SessionSettings(
                    savePath: $savePath,
                    individualName: AbstractSessionHandlerTest::SESSION_NAME,
                ),
                defaultSavePath: '/not/used',
                clock: new FixedClock(now: new DateTimeImmutable(datetime: '@' . $now)),
            );
            $sessionId = $sessionHandler->getId();
            $session = $_SESSION;
            session_write_close();
        } finally {
            $this->removeSessionSavePath(savePath: $savePath);
        }

        $this->assertNotSame(AbstractSessionHandlerTest::COOKIE_SESSION_ID, $sessionId);
        $this->assertSame(
            [
                'yuf' => [
                    'handler' => [
                        'sessionCreated' => $now,
                        'trustedRemoteAddress' => $remoteAddress,
                        'trustedUserAgent' => $userAgent,
                        'lastActivity' => $now,
                    ],
                ],
            ],
            $session,
        );
    }

    /**
     * After 30 minutes the session ID is regenerated (against stolen IDs), the data is kept.
     */
    #[RunInSeparateProcess]
    public function testSessionOlderThanThirtyMinutesGetsANewIdAndKeepsItsData(): void
    {
        $savePath = $this->createSessionSavePath();
        $this->writeExistingSession(savePath: $savePath, lastActivity: 1_790_001_900);
        $_COOKIE[AbstractSessionHandlerTest::SESSION_NAME] = AbstractSessionHandlerTest::COOKIE_SESSION_ID;
        $httpRequest = HttpRequestFactory::create(
            headers: ['User-Agent' => 'Browser'],
            cookies: [AbstractSessionHandlerTest::SESSION_NAME => AbstractSessionHandlerTest::COOKIE_SESSION_ID],
        );

        try {
            $sessionHandler = new FileSessionHandler(
                httpRequest: $httpRequest,
                sessionSettings: new SessionSettings(
                    savePath: $savePath,
                    individualName: AbstractSessionHandlerTest::SESSION_NAME,
                ),
                defaultSavePath: '/not/used',
                clock: new FixedClock(now: new DateTimeImmutable(datetime: '@1790001801')),
            );
            $sessionId = $sessionHandler->getId();
            $session = $_SESSION;
            $oldSessionFileExists = file_exists(
                filename: $savePath . DIRECTORY_SEPARATOR . 'sess_' . AbstractSessionHandlerTest::COOKIE_SESSION_ID,
            );
            session_write_close();
        } finally {
            $this->removeSessionSavePath(savePath: $savePath);
        }

        $this->assertNotSame(AbstractSessionHandlerTest::COOKIE_SESSION_ID, $sessionId);
        $this->assertFalse($oldSessionFileExists);
        $this->assertSame(
            [
                'yuf' => [
                    'handler' => [
                        'sessionCreated' => 1_790_001_801,
                        'trustedRemoteAddress' => '192.0.2.1',
                        'trustedUserAgent' => 'Browser',
                        'lastActivity' => 1_790_001_801,
                    ],
                    'tables' => ['items' => []],
                ],
            ],
            $session,
        );
    }

    #[RunInSeparateProcess]
    public function testSessionYoungerThanThirtyMinutesKeepsItsId(): void
    {
        $savePath = $this->createSessionSavePath();
        $this->writeExistingSession(savePath: $savePath, lastActivity: 1_790_001_000);
        $_COOKIE[AbstractSessionHandlerTest::SESSION_NAME] = AbstractSessionHandlerTest::COOKIE_SESSION_ID;
        $httpRequest = HttpRequestFactory::create(
            headers: ['User-Agent' => 'Browser'],
            cookies: [AbstractSessionHandlerTest::SESSION_NAME => AbstractSessionHandlerTest::COOKIE_SESSION_ID],
        );

        try {
            $sessionHandler = new FileSessionHandler(
                httpRequest: $httpRequest,
                sessionSettings: new SessionSettings(
                    savePath: $savePath,
                    individualName: AbstractSessionHandlerTest::SESSION_NAME,
                ),
                defaultSavePath: '/not/used',
                clock: new FixedClock(now: new DateTimeImmutable(datetime: '@1790001800')),
            );
            $sessionId = $sessionHandler->getId();
            session_write_close();
        } finally {
            $this->removeSessionSavePath(savePath: $savePath);
        }

        $this->assertSame(AbstractSessionHandlerTest::COOKIE_SESSION_ID, $sessionId);
    }

    /**
     * Regression test for session fixation: PHP ignores the strict mode of a handler without `validateId()`, so a
     * well-formed session ID that this server never issued (set by an attacker as cookie) was accepted as it was.
     */
    #[RunInSeparateProcess]
    public function testUnknownSessionIdOfTheCookieIsReplaced(): void
    {
        $savePath = $this->createSessionSavePath();
        $unknownSessionId = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $_COOKIE[AbstractSessionHandlerTest::SESSION_NAME] = $unknownSessionId;
        $httpRequest = HttpRequestFactory::create(
            cookies: [AbstractSessionHandlerTest::SESSION_NAME => $unknownSessionId],
        );

        try {
            $sessionHandler = new FileSessionHandler(
                httpRequest: $httpRequest,
                sessionSettings: new SessionSettings(
                    savePath: $savePath,
                    individualName: AbstractSessionHandlerTest::SESSION_NAME,
                ),
                defaultSavePath: '/not/used',
            );
            $sessionId = $sessionHandler->getId();
            session_write_close();
        } finally {
            $this->removeSessionSavePath(savePath: $savePath);
        }

        $this->assertNotSame($unknownSessionId, $sessionId);
    }

    #[RunInSeparateProcess]
    public function testOnlyExistingSessionsAreValidIds(): void
    {
        $savePath = $this->createSessionSavePath();
        $_COOKIE[AbstractSessionHandlerTest::SESSION_NAME] = AbstractSessionHandlerTest::COOKIE_SESSION_ID;
        $httpRequest = HttpRequestFactory::create(
            cookies: [AbstractSessionHandlerTest::SESSION_NAME => AbstractSessionHandlerTest::COOKIE_SESSION_ID],
        );

        try {
            $sessionHandler = new FileSessionHandler(
                httpRequest: $httpRequest,
                sessionSettings: new SessionSettings(
                    savePath: $savePath,
                    individualName: AbstractSessionHandlerTest::SESSION_NAME,
                ),
                defaultSavePath: '/not/used',
            );
            $sessionHandler->ensureStarted();
            $existing = $sessionHandler->validateId(id: AbstractSessionHandlerTest::REQUESTED_SESSION_ID);
            $unknown = $sessionHandler->validateId(id: 'bbbbbbbbbbbbbbbbbbbbbbbb');
            $path = $sessionHandler->validateId(id: '../' . AbstractSessionHandlerTest::REQUESTED_SESSION_ID);
            session_write_close();
        } finally {
            $this->removeSessionSavePath(savePath: $savePath);
        }

        $this->assertTrue($existing);
        $this->assertFalse($unknown);
        $this->assertFalse($path);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSessionIdProvider(): iterable
    {
        yield 'path' => ['../../etc/passwd'];
        yield 'space' => ['abc def'];
        yield 'umlaut' => ['abcäöü'];
        yield 'upper case with default characters' => ['ABCDEF0123456789'];
    }

    /**
     * An invalid session ID in the cookie is ignored (PHP would warn), the user gets a new session.
     */
    #[DataProvider('invalidSessionIdProvider')]
    #[RunInSeparateProcess]
    public function testInvalidSessionIdOfTheCookieIsReplaced(string $invalidSessionId): void
    {
        $savePath = $this->createSessionSavePath();
        $_COOKIE[AbstractSessionHandlerTest::SESSION_NAME] = $invalidSessionId;
        $httpRequest = HttpRequestFactory::create(
            cookies: [AbstractSessionHandlerTest::SESSION_NAME => $invalidSessionId],
        );

        try {
            $sessionHandler = new FileSessionHandler(
                httpRequest: $httpRequest,
                sessionSettings: new SessionSettings(
                    savePath: $savePath,
                    individualName: AbstractSessionHandlerTest::SESSION_NAME,
                ),
                defaultSavePath: '/not/used',
            );
            $sessionId = $sessionHandler->getId();
            session_write_close();
        } finally {
            $this->removeSessionSavePath(savePath: $savePath);
        }

        $this->assertNotSame($invalidSessionId, $sessionId);
        $this->assertMatchesRegularExpression('/^[a-v0-9]+$|^[a-f0-9]+$/', $sessionId);
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function sameSiteProvider(): iterable
    {
        yield 'strict' => [true, 'Strict'];
        yield 'lax' => [false, 'Lax'];
    }

    #[DataProvider('sameSiteProvider')]
    #[RunInSeparateProcess]
    public function testSessionCookieIsSecureHttpOnlyAndSameSite(bool $isSameSiteStrict, string $expectedSameSite): void
    {
        $savePath = $this->createSessionSavePath();
        $_COOKIE[AbstractSessionHandlerTest::SESSION_NAME] = AbstractSessionHandlerTest::COOKIE_SESSION_ID;
        $httpRequest = HttpRequestFactory::create(
            cookies: [AbstractSessionHandlerTest::SESSION_NAME => AbstractSessionHandlerTest::COOKIE_SESSION_ID],
        );

        try {
            $sessionHandler = new FileSessionHandler(
                httpRequest: $httpRequest,
                sessionSettings: new SessionSettings(
                    savePath: $savePath,
                    individualName: AbstractSessionHandlerTest::SESSION_NAME,
                    isSameSiteStrict: $isSameSiteStrict,
                ),
                defaultSavePath: '/not/used',
            );
            $sessionHandler->ensureStarted();
            $parameters = session_get_cookie_params();
            $useStrictMode = ini_get(option: 'session.use_strict_mode');
            $useOnlyCookies = ini_get(option: 'session.use_only_cookies');
            session_write_close();
        } finally {
            $this->removeSessionSavePath(savePath: $savePath);
        }

        $this->assertTrue($parameters['secure']);
        $this->assertTrue($parameters['httponly']);
        $this->assertSame($expectedSameSite, $parameters['samesite']);
        $this->assertSame('1', $useStrictMode);
        $this->assertSame('1', $useOnlyCookies);
    }

    #[RunInSeparateProcess]
    public function testRegenerateIdGivesANewIdAndDeletesTheOldSession(): void
    {
        $savePath = $this->createSessionSavePath();
        $_COOKIE[AbstractSessionHandlerTest::SESSION_NAME] = AbstractSessionHandlerTest::COOKIE_SESSION_ID;
        $httpRequest = HttpRequestFactory::create(
            cookies: [AbstractSessionHandlerTest::SESSION_NAME => AbstractSessionHandlerTest::COOKIE_SESSION_ID],
        );

        try {
            $sessionHandler = new FileSessionHandler(
                httpRequest: $httpRequest,
                sessionSettings: new SessionSettings(
                    savePath: $savePath,
                    individualName: AbstractSessionHandlerTest::SESSION_NAME,
                ),
                defaultSavePath: '/not/used',
                clock: new FixedClock(now: new DateTimeImmutable(datetime: '@1790000000')),
            );
            $sessionHandler->regenerateId();
            $newId = $sessionHandler->getId();
            $oldSessionFileExists = file_exists(
                filename: $savePath . DIRECTORY_SEPARATOR . 'sess_' . AbstractSessionHandlerTest::COOKIE_SESSION_ID,
            );
            session_write_close();
        } finally {
            $this->removeSessionSavePath(savePath: $savePath);
        }

        $this->assertNotSame(AbstractSessionHandlerTest::COOKIE_SESSION_ID, $newId);
        $this->assertFalse($oldSessionFileExists);
    }

    #[RunInSeparateProcess]
    public function testSameSiteCanBeChangedForTheRestOfTheRequest(): void
    {
        $savePath = $this->createSessionSavePath();
        $_COOKIE[AbstractSessionHandlerTest::SESSION_NAME] = AbstractSessionHandlerTest::COOKIE_SESSION_ID;
        $httpRequest = HttpRequestFactory::create(
            cookies: [AbstractSessionHandlerTest::SESSION_NAME => AbstractSessionHandlerTest::COOKIE_SESSION_ID],
        );

        try {
            $sessionHandler = new FileSessionHandler(
                httpRequest: $httpRequest,
                sessionSettings: new SessionSettings(
                    savePath: $savePath,
                    individualName: AbstractSessionHandlerTest::SESSION_NAME,
                ),
                defaultSavePath: '/not/used',
            );
            $sessionHandler->ensureStarted();
            $sessionHandler->changeCookieSameSiteToNone();
            $none = session_get_cookie_params();
            $sessionHandler->changeCookieSameSiteToLax();
            $lax = session_get_cookie_params();
            session_write_close();
        } finally {
            $this->removeSessionSavePath(savePath: $savePath);
        }

        $this->assertSame('None', $none['samesite']);
        $this->assertTrue($none['secure']);
        $this->assertSame('Lax', $lax['samesite']);
        $this->assertTrue($lax['secure']);
    }

    /**
     * A session of the same client without the time of the last activity or without the trusted client is not
     * trusted: it is replaced (fail closed).
     *
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function incompleteHandlerDataProvider(): iterable
    {
        yield 'no last activity' => [
            ['sessionCreated' => 1_790_000_000, 'trustedRemoteAddress' => '192.0.2.1', 'trustedUserAgent' => 'Browser'],
        ];
        yield 'no trusted remote address' => [
            ['sessionCreated' => 1_790_000_000, 'trustedUserAgent' => 'Browser', 'lastActivity' => 1_790_000_100],
        ];
        yield 'no trusted user agent' => [
            ['sessionCreated' => 1_790_000_000, 'trustedRemoteAddress' => '192.0.2.1', 'lastActivity' => 1_790_000_100],
        ];
    }

    /**
     * @param array<string, mixed> $handlerData
     */
    #[DataProvider('incompleteHandlerDataProvider')]
    #[RunInSeparateProcess]
    public function testSessionWithIncompleteHandlerDataIsReplaced(array $handlerData): void
    {
        $savePath = $this->createSessionSavePath();
        file_put_contents(
            filename: $savePath . DIRECTORY_SEPARATOR . 'sess_' . AbstractSessionHandlerTest::COOKIE_SESSION_ID,
            data: AbstractSessionHandlerTest::serialized(
                data: ['yuf' => ['handler' => $handlerData, 'auth' => ['isLoggedIn' => true, 'authSessionId' => 5]]],
            ),
        );
        $_COOKIE[AbstractSessionHandlerTest::SESSION_NAME] = AbstractSessionHandlerTest::COOKIE_SESSION_ID;
        $httpRequest = HttpRequestFactory::create(
            headers: ['User-Agent' => 'Browser'],
            cookies: [AbstractSessionHandlerTest::SESSION_NAME => AbstractSessionHandlerTest::COOKIE_SESSION_ID],
        );

        try {
            $sessionHandler = new FileSessionHandler(
                httpRequest: $httpRequest,
                sessionSettings: new SessionSettings(
                    savePath: $savePath,
                    individualName: AbstractSessionHandlerTest::SESSION_NAME,
                ),
                defaultSavePath: '/not/used',
                clock: new FixedClock(now: new DateTimeImmutable(datetime: '@1790000200')),
            );
            $sessionId = $sessionHandler->getId();
            $session = $_SESSION;
            session_write_close();
        } finally {
            $this->removeSessionSavePath(savePath: $savePath);
        }

        $this->assertNotSame(AbstractSessionHandlerTest::COOKIE_SESSION_ID, $sessionId);
        $this->assertSame(
            [
                'yuf' => [
                    'handler' => [
                        'sessionCreated' => 1_790_000_200,
                        'trustedRemoteAddress' => '192.0.2.1',
                        'trustedUserAgent' => 'Browser',
                        'lastActivity' => 1_790_000_200,
                    ],
                ],
            ],
            $session,
        );
    }

    /**
     * The save path is created with mode 0700 (only the web server user may read the sessions).
     */
    #[RunInSeparateProcess]
    public function testMissingSavePathIsCreatedForTheOwnerOnly(): void
    {
        $savePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-session-test-' . bin2hex(string: random_bytes(length: 8))
            . DIRECTORY_SEPARATOR . 'nested';
        $httpRequest = HttpRequestFactory::create();

        try {
            $sessionHandler = new FileSessionHandler(
                httpRequest: $httpRequest,
                sessionSettings: new SessionSettings(
                    savePath: $savePath,
                    individualName: AbstractSessionHandlerTest::SESSION_NAME,
                ),
                defaultSavePath: '/not/used',
            );
            $sessionHandler->ensureStarted();
            $permissions = fileperms(filename: $savePath) & 0o777;
            session_write_close();
        } finally {
            $this->removeSessionSavePath(savePath: $savePath);
            rmdir(directory: dirname(path: $savePath));
        }

        $this->assertSame(0o700, $permissions);
    }

    #[RunInSeparateProcess]
    public function testSavePathThatCannotBeCreatedIsReported(): void
    {
        $blocker = tempnam(directory: sys_get_temp_dir(), prefix: 'yuf-session-test-');
        if ($blocker === false) {
            AbstractSessionHandlerTest::fail('Cannot create a temporary file.');
        }
        $httpRequest = HttpRequestFactory::create();

        try {
            $this->expectException(RuntimeException::class);

            $sessionHandler = new FileSessionHandler(
                httpRequest: $httpRequest,
                sessionSettings: new SessionSettings(savePath: $blocker . DIRECTORY_SEPARATOR . 'sessions'),
                defaultSavePath: '/not/used',
            );
            $sessionHandler->ensureStarted();
        } finally {
            unlink(filename: $blocker);
        }
    }

    /**
     * The session starts on its first use: construction and the SameSite changes start nothing (no lock, no cookie, no
     * file), `ensureStarted()` starts it once.
     */
    #[RunInSeparateProcess]
    public function testSessionStartsOnFirstUseOnly(): void
    {
        $savePath = $this->createSessionSavePath();
        $_COOKIE[AbstractSessionHandlerTest::SESSION_NAME] = AbstractSessionHandlerTest::COOKIE_SESSION_ID;
        $httpRequest = HttpRequestFactory::create(
            cookies: [AbstractSessionHandlerTest::SESSION_NAME => AbstractSessionHandlerTest::COOKIE_SESSION_ID],
        );

        try {
            $sessionHandler = new FileSessionHandler(
                httpRequest: $httpRequest,
                sessionSettings: new SessionSettings(
                    savePath: $savePath,
                    individualName: AbstractSessionHandlerTest::SESSION_NAME,
                ),
                defaultSavePath: '/not/used',
            );
            $sessionHandler->changeCookieSameSiteToLax();
            $sessionHandler->changeCookieSameSiteToNone();
            $statusBefore = session_status();
            $isStartedBefore = $sessionHandler->isStarted();
            $sameSiteBefore = session_get_cookie_params()['samesite'];
            $nameBefore = session_name();
            $sessionHandler->ensureStarted();
            $sessionId = $sessionHandler->getId();
            $sessionHandler->ensureStarted();
            $statusAfter = session_status();
            $idAfter = session_id();
            session_write_close();
        } finally {
            $this->removeSessionSavePath(savePath: $savePath);
        }

        $this->assertSame(PHP_SESSION_NONE, $statusBefore);
        $this->assertFalse($isStartedBefore);
        $this->assertNotSame('Lax', $sameSiteBefore);
        $this->assertNotSame('None', $sameSiteBefore);
        $this->assertNotSame(AbstractSessionHandlerTest::SESSION_NAME, $nameBefore);
        $this->assertSame(PHP_SESSION_ACTIVE, $statusAfter);
        $this->assertTrue($sessionHandler->isStarted());
        $this->assertSame($idAfter, $sessionId);
        $this->assertSame(AbstractSessionHandlerTest::COOKIE_SESSION_ID, $sessionId);
    }

    /**
     * The first access through the storage (read or write) starts the session.
     */
    #[DataProvider('storageAccessProvider')]
    #[RunInSeparateProcess]
    public function testStorageAccessStartsTheSession(bool $isWrite): void
    {
        $savePath = $this->createSessionSavePath();
        $httpRequest = HttpRequestFactory::create();

        try {
            $sessionHandler = new FileSessionHandler(
                httpRequest: $httpRequest,
                sessionSettings: new SessionSettings(
                    savePath: $savePath,
                    individualName: AbstractSessionHandlerTest::SESSION_NAME,
                ),
                defaultSavePath: '/not/used',
            );
            $session = new Session(storage: new NativeSessionStorage(sessionHandler: $sessionHandler));
            $isStartedBefore = $sessionHandler->isStarted();
            if ($isWrite) {
                $session->set(key: 'cart', value: 'full');
            } else {
                $session->getString(key: 'cart');
            }
            $isStartedAfter = $sessionHandler->isStarted();
            $statusAfter = session_status();
            session_write_close();
        } finally {
            $this->removeSessionSavePath(savePath: $savePath);
        }

        $this->assertFalse($isStartedBefore);
        $this->assertTrue($isStartedAfter);
        $this->assertSame(PHP_SESSION_ACTIVE, $statusAfter);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function storageAccessProvider(): iterable
    {
        yield 'read' => [false];
        yield 'write' => [true];
    }

    /**
     * `writeClose()` writes the session and releases the lock; the data stays readable, writing and changing the
     * cookie throw (the change would be lost).
     */
    #[RunInSeparateProcess]
    public function testClosedSessionIsWrittenCanBeReadButNotChanged(): void
    {
        $savePath = $this->createSessionSavePath();
        $_COOKIE[AbstractSessionHandlerTest::SESSION_NAME] = AbstractSessionHandlerTest::COOKIE_SESSION_ID;
        $httpRequest = HttpRequestFactory::create(
            cookies: [AbstractSessionHandlerTest::SESSION_NAME => AbstractSessionHandlerTest::COOKIE_SESSION_ID],
        );

        try {
            $sessionHandler = new FileSessionHandler(
                httpRequest: $httpRequest,
                sessionSettings: new SessionSettings(
                    savePath: $savePath,
                    individualName: AbstractSessionHandlerTest::SESSION_NAME,
                ),
                defaultSavePath: '/not/used',
            );
            $session = new Session(storage: new NativeSessionStorage(sessionHandler: $sessionHandler));
            $session->set(key: 'cart', value: 'full');
            $sessionHandler->writeClose();
            $sessionHandler->writeClose();
            $statusAfterClose = session_status();
            $stored = file_get_contents(
                filename: $savePath . DIRECTORY_SEPARATOR . 'sess_' . AbstractSessionHandlerTest::COOKIE_SESSION_ID,
            );
            $read = $session->getString(key: 'cart');
            $sessionId = $session->getId();
            $failures = [];
            foreach (
                [
                    fn() => $session->set(key: 'cart', value: 'empty'),
                    fn() => $session->regenerateId(),
                    fn() => $sessionHandler->regenerateId(),
                    fn() => $sessionHandler->changeCookieSameSiteToLax(),
                    fn() => $sessionHandler->changeCookieSameSiteToNone(),
                ] as $change
            ) {
                try {
                    $change();
                } catch (LogicException $logicException) {
                    $failures[] = $logicException->getMessage();
                }
            }
            $sessionHandler->ensureStarted();
        } finally {
            $this->removeSessionSavePath(savePath: $savePath);
        }

        $this->assertSame(PHP_SESSION_NONE, $statusAfterClose);
        $this->assertTrue($sessionHandler->isClosed());
        $this->assertIsString($stored);
        $this->assertStringContainsString('full', $stored);
        $this->assertSame('full', $read);
        $this->assertSame(AbstractSessionHandlerTest::COOKIE_SESSION_ID, $sessionId);
        $this->assertCount(5, $failures);
        foreach ($failures as $failure) {
            $this->assertSame('The session is closed: it cannot be changed any more.', $failure);
        }
    }

    public function testSessionClosedBeforeItsFirstUseCannotBeStarted(): void
    {
        $sessionHandler = new FileSessionHandler(
            httpRequest: HttpRequestFactory::create(),
            sessionSettings: new SessionSettings(),
            defaultSavePath: '/not/used',
        );

        $sessionHandler->writeClose();

        $this->assertTrue($sessionHandler->isClosed());
        $this->assertFalse($sessionHandler->isStarted());
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The session cannot be started: it was closed before its first use.');

        $sessionHandler->ensureStarted();
    }

    /**
     * A start after the response was sent (output reached the client) cannot send the session cookie any more: it
     * fails with a clear message instead of a PHP warning.
     */
    public function testSessionCannotBeStartedAfterTheOutputWasSent(): void
    {
        $sessionHandler = new OutputSentSessionHandler();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs(
            'The session cannot be started after the response was sent: access the session while the view runs.',
        );

        $sessionHandler->ensureStarted();
    }

    private function writeExistingSession(string $savePath, int $lastActivity): void
    {
        file_put_contents(
            filename: $savePath . DIRECTORY_SEPARATOR . 'sess_' . AbstractSessionHandlerTest::COOKIE_SESSION_ID,
            data: AbstractSessionHandlerTest::serialized(data: [
                'yuf' => [
                    'handler' => [
                        'sessionCreated' => 1_790_000_000,
                        'trustedRemoteAddress' => '192.0.2.1',
                        'trustedUserAgent' => 'Browser',
                        'lastActivity' => $lastActivity,
                    ],
                    'tables' => ['items' => []],
                ],
            ]),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function serialized(array $data): string
    {
        $encoded = '';
        foreach ($data as $key => $value) {
            $encoded .= $key . '|' . serialize(value: $value);
        }

        return $encoded;
    }

    #[RunInSeparateProcess]
    public function testDefaultSavePathIsUsedWithoutSavePathInSettings(): void
    {
        $defaultSavePath = $this->createSessionSavePath();
        $_COOKIE[AbstractSessionHandlerTest::SESSION_NAME] = AbstractSessionHandlerTest::COOKIE_SESSION_ID;
        $httpRequest = HttpRequestFactory::create(
            cookies: [AbstractSessionHandlerTest::SESSION_NAME => AbstractSessionHandlerTest::COOKIE_SESSION_ID],
        );

        try {
            $sessionHandler = new FileSessionHandler(
                httpRequest: $httpRequest,
                sessionSettings: new SessionSettings(individualName: AbstractSessionHandlerTest::SESSION_NAME),
                defaultSavePath: $defaultSavePath,
            );
            $sessionId = $sessionHandler->getId();
            $usedSavePath = session_save_path();
            session_write_close();
        } finally {
            $this->removeSessionSavePath(savePath: $defaultSavePath);
        }

        $this->assertSame(AbstractSessionHandlerTest::COOKIE_SESSION_ID, $sessionId);
        $this->assertSame($defaultSavePath, $usedSavePath);
    }

    /**
     * @param array<string, string> $cookies
     */
    private function createHandler(
        array $cookies,
        string $individualName,
        string $savePath = '/not/used',
    ): FileSessionHandler {
        return new FileSessionHandler(
            httpRequest: HttpRequestFactory::create(cookies: $cookies),
            sessionSettings: new SessionSettings(savePath: $savePath, individualName: $individualName),
            defaultSavePath: '/not/used',
        );
    }

    /**
     * @return array<string, array{array<string, string>, string, bool}> cookies, individual name, expected
     */
    public static function isActiveProvider(): array
    {
        $name = AbstractSessionHandlerTest::SESSION_NAME;
        $id = AbstractSessionHandlerTest::COOKIE_SESSION_ID;

        return [
            'no cookie' => [[], $name, false],
            'valid cookie' => [[$name => $id], $name, true],
            'valid cookie, default name' => [['PHPSESSID' => $id], '', true],
            'invalid id' => [[$name => 'not valid!'], $name, false],
            'empty id' => [[$name => ''], $name, false],
            'too long id' => [[$name => str_repeat(string: 'a', times: 257)], $name, false],
            'cookie of another name' => [['other' => $id], $name, false],
            'default name expected, individual name sent' => [[$name => $id], '', false],
        ];
    }

    /**
     * `isActive()` reads the request only: nothing is started, no lock, no cookie, and the session name of PHP stays.
     *
     * @param array<string, string> $cookies
     */
    #[DataProvider('isActiveProvider')]
    #[RunInSeparateProcess]
    public function testIsActiveLooksForAValidSessionCookieWithoutStartingTheSession(
        array $cookies,
        string $individualName,
        bool $expected,
    ): void {
        $sessionHandler = $this->createHandler(cookies: $cookies, individualName: $individualName);
        $nameBefore = session_name();

        $isActive = $sessionHandler->isActive();

        $this->assertSame($expected, $isActive);
        $this->assertFalse($sessionHandler->isStarted());
        $this->assertSame(PHP_SESSION_NONE, session_status());
        $this->assertSame($nameBefore, session_name());
    }

    #[RunInSeparateProcess]
    public function testSessionStartedInTheRequestIsActiveWithoutCookie(): void
    {
        $savePath = $this->createSessionSavePath();

        try {
            $sessionHandler = $this->createHandler(
                cookies: [],
                individualName: AbstractSessionHandlerTest::SESSION_NAME,
                savePath: $savePath,
            );
            $isActiveBefore = $sessionHandler->isActive();
            $sessionHandler->ensureStarted();
            $isActiveAfter = $sessionHandler->isActive();
            $sessionHandler->writeClose();
            $isActiveAfterClose = $sessionHandler->isActive();
        } finally {
            $this->removeSessionSavePath(savePath: $savePath);
        }

        $this->assertFalse($isActiveBefore);
        $this->assertTrue($isActiveAfter);
        $this->assertTrue($isActiveAfterClose);
    }

    /**
     * Creates a save path with an existing session for the ID of the cookie and of the request input, so the strict
     * mode of PHP accepts both IDs.
     */
    private function createSessionSavePath(): string
    {
        $savePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-session-test-' . bin2hex(string: random_bytes(length: 8));
        mkdir(directory: $savePath);
        foreach ([AbstractSessionHandlerTest::COOKIE_SESSION_ID, AbstractSessionHandlerTest::REQUESTED_SESSION_ID] as $sessionId) {
            file_put_contents(filename: $savePath . DIRECTORY_SEPARATOR . 'sess_' . $sessionId, data: '');
        }

        return $savePath;
    }

    private function removeSessionSavePath(string $savePath): void
    {
        $sessionFilePaths = glob(pattern: $savePath . DIRECTORY_SEPARATOR . 'sess_*');
        foreach ($sessionFilePaths === false ? [] : $sessionFilePaths as $sessionFilePath) {
            unlink(filename: $sessionFilePath);
        }
        rmdir(directory: $savePath);
    }
}
