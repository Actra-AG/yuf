<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\AuthSession;
use actra\yuf\security\CsrfToken;
use actra\yuf\session\AbstractSessionHandler;
use Override;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use UnexpectedValueException;

/**
 * A logged-in logout regenerates the session ID, so the registered session handler is replaced by a stand-in that only
 * counts the regenerations; the session array and the registered handler are reset after each test.
 */
final class AuthSessionTest extends TestCase
{
    private ReflectionProperty $handlerProperty;

    #[Override]
    protected function setUp(): void
    {
        $this->handlerProperty = new ReflectionProperty(
            class: AbstractSessionHandler::class,
            property: 'abstractSessionHandler',
        );
        $_SESSION = [];
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->handlerProperty->setValue(null, null);
        unset($_SESSION);
    }

    public function testLogInStoresTheAuthSessionId(): void
    {
        AuthSession::logIn(authSessionId: 5);

        $this->assertTrue(AuthSession::isLoggedIn());
        $this->assertSame(5, AuthSession::getAuthSessionId());
    }

    public function testLogInStoresTheAuthSessionIdInTheSession(): void
    {
        AuthSession::logIn(authSessionId: 5);

        $this->assertSame(['auth_userSession' => ['isLoggedIn' => true, 'authSessionId' => 5]], $_SESSION);
    }

    public function testIsNotLoggedInWithoutLogIn(): void
    {
        $this->assertFalse(AuthSession::isLoggedIn());
    }

    public function testAuthSessionIDWithoutLogInThrows(): void
    {
        $this->expectException(UnexpectedValueException::class);

        AuthSession::getAuthSessionId();
    }

    public function testLogOutClearsUserDataAndRegeneratesTheSessionId(): void
    {
        $sessionHandler = $this->registerSessionHandler();
        AuthSession::logIn(authSessionId: 5);
        $_SESSION['sessionCreated'] = 1_790_000_000;
        $_SESSION['preferredLanguage'] = 'de';
        $_SESSION[CsrfToken::CSRFTOKENSTORAGE] = 'token';
        $_SESSION['sess_breadcrumb'] = ['home' => ['title' => 'Home', 'link' => 'home']];

        AuthSession::logOut();

        $this->assertSame(
            [
                'sessionCreated' => 1_790_000_000,
                'preferredLanguage' => 'de',
                'auth_userSession' => ['isLoggedIn' => false, 'authSessionId' => 0],
            ],
            $_SESSION,
        );
        $this->assertFalse(AuthSession::isLoggedIn());
        $this->assertSame(0, AuthSession::getAuthSessionId());
        $this->assertSame(1, $sessionHandler->regenerations);
    }

    public function testLogOutWithoutLogInKeepsTheSession(): void
    {
        $sessionHandler = $this->registerSessionHandler();
        $_SESSION['sess_breadcrumb'] = ['home' => ['title' => 'Home', 'link' => 'home']];

        AuthSession::logOut();

        $this->assertSame(
            [
                'sess_breadcrumb' => ['home' => ['title' => 'Home', 'link' => 'home']],
                'auth_userSession' => ['isLoggedIn' => false],
            ],
            $_SESSION,
        );
        $this->assertSame(0, $sessionHandler->regenerations);
    }

    public function testSessionWithoutAuthSessionIdIsLoggedOut(): void
    {
        $sessionHandler = $this->registerSessionHandler();
        $_SESSION['auth_userSession'] = ['isLoggedIn' => true, 'authSessionID' => 5];

        $this->assertFalse(AuthSession::isLoggedIn());
        $this->assertSame(['auth_userSession' => ['isLoggedIn' => false, 'authSessionId' => 0]], $_SESSION);
        $this->assertSame(1, $sessionHandler->regenerations);
    }

    private function registerSessionHandler(): AuthSessionTestSessionHandler
    {
        $sessionHandler = new AuthSessionTestSessionHandler();
        $this->handlerProperty->setValue(null, $sessionHandler);

        return $sessionHandler;
    }
}

final class AuthSessionTestSessionHandler extends AbstractSessionHandler
{
    public int $regenerations = 0;

    /**
     * Does not start a session.
     */
    public function __construct() {} // @phpstan-ignore constructor.missingParentCall (the parent starts a session)

    #[Override]
    protected function executePreStartActions(): void {}

    #[Override]
    public function regenerateId(): void
    {
        $this->regenerations++;
    }
}
