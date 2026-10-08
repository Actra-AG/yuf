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
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

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
            new FileSessionHandler(
                httpRequest: $httpRequest,
                sessionSettings: new SessionSettings(
                    savePath: $savePath,
                    individualName: AbstractSessionHandlerTest::SESSION_NAME,
                ),
                defaultSavePath: '/not/used',
                clock: new FixedClock(now: new DateTimeImmutable(datetime: '@1790000000')),
            );
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
            new FileSessionHandler(
                httpRequest: $httpRequest,
                sessionSettings: new SessionSettings(
                    savePath: $savePath,
                    individualName: AbstractSessionHandlerTest::SESSION_NAME,
                ),
                defaultSavePath: '/not/used',
                clock: new FixedClock(now: new DateTimeImmutable(datetime: '@1790000200')),
            );
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
