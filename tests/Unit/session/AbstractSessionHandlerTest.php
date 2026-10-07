<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\session;

use actra\yuf\security\CsrfToken;
use actra\yuf\session\AbstractSessionHandler;
use actra\yuf\session\FileSessionHandler;
use actra\yuf\session\SessionSettings;
use actra\yuf\tests\Double\CoreTestInstance;
use Override;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class AbstractSessionHandlerTest extends TestCase
{
    private const string SESSION_NAME = 'yufTestSession';
    private const string COOKIE_SESSION_ID = '0123456789abcdef0123456789abcdef';
    private const string REQUESTED_SESSION_ID = 'fedcba9876543210fedcba9876543210';
    private const array DATA_WITHOUT_USER_DATA = [
        'sessionCreated' => 1_790_000_000,
        'trustedRemoteAddress' => '192.0.2.1',
        'trustedUserAgent' => 'Browser',
        'lastActivity' => 1_790_000_100,
        'preferredLanguage' => 'de',
    ];

    #[Override]
    protected function tearDown(): void
    {
        unset($_SESSION);
    }

    public function testClearUserDataRemovesUserData(): void
    {
        $_SESSION = AbstractSessionHandlerTest::DATA_WITHOUT_USER_DATA;
        $_SESSION['auth_userSession'] = ['isLoggedIn' => true, 'authSessionId' => 5];
        $_SESSION[CsrfToken::CSRFTOKENSTORAGE] = 'token';
        $_SESSION['sess_breadcrumb'] = ['home' => ['title' => 'Home', 'link' => 'home']];

        AbstractSessionHandler::clearUserData();

        $this->assertSame(AbstractSessionHandlerTest::DATA_WITHOUT_USER_DATA, $_SESSION);
    }

    public function testClearUserDataKeepsSessionHandlerDataAndLanguage(): void
    {
        $_SESSION = AbstractSessionHandlerTest::DATA_WITHOUT_USER_DATA;

        AbstractSessionHandler::clearUserData();

        $this->assertSame(AbstractSessionHandlerTest::DATA_WITHOUT_USER_DATA, $_SESSION);
    }

    public function testClearUserDataDoesNothingWithoutSession(): void
    {
        unset($_SESSION);

        AbstractSessionHandler::clearUserData();

        $this->assertFalse(AbstractSessionHandler::enabled());
    }

    /**
     * Regression test for session fixation: a session ID in the URL or the POST data must not replace the session ID
     * of the cookie, even if a session with that ID exists.
     */
    #[RunInSeparateProcess]
    public function testSessionIdIsNotReadFromRequestInput(): void
    {
        $savePath = $this->createSessionSavePath();
        $_COOKIE[AbstractSessionHandlerTest::SESSION_NAME] = AbstractSessionHandlerTest::COOKIE_SESSION_ID;
        $_GET[AbstractSessionHandlerTest::SESSION_NAME] = AbstractSessionHandlerTest::REQUESTED_SESSION_ID;
        $_POST[AbstractSessionHandlerTest::SESSION_NAME] = AbstractSessionHandlerTest::REQUESTED_SESSION_ID;

        try {
            $sessionHandler = new FileSessionHandler(sessionSettings: new SessionSettings(
                savePath: $savePath,
                individualName: AbstractSessionHandlerTest::SESSION_NAME,
            ));
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
     * Creates a save path with an existing session for the ID of the cookie and of the request input, so the strict
     * mode of PHP accepts both IDs.
     */
    private function createSessionSavePath(): string
    {
        $savePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-session-test-' . bin2hex(string: random_bytes(length: 8));
        mkdir(directory: $savePath);
        CoreTestInstance::register(cacheDirectory: $savePath . DIRECTORY_SEPARATOR);
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
