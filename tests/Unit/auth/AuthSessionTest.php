<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\AuthSession;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\session\SessionSectionEnum;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * `AuthSession` on an `ArraySessionStorage`. The layout of the stored state (`yuf.auth` with `isLoggedIn` and
 * `authSessionId`) is pinned in `testStorageLayoutAfterLogIn()`; the other tests only use the public methods and the
 * session ID (a logout regenerates it).
 */
final class AuthSessionTest extends TestCase
{
    private ArraySessionStorage $storage;
    private Session $session;
    private AuthSession $authSession;

    #[Override]
    protected function setUp(): void
    {
        $this->storage = new ArraySessionStorage();
        $this->session = new Session(storage: $this->storage);
        $this->authSession = new AuthSession(session: $this->session);
    }

    public function testLogInStoresTheAuthSessionId(): void
    {
        $this->authSession->logIn(authSessionId: 5);

        $this->assertTrue($this->authSession->isLoggedIn());
        $this->assertSame(5, $this->authSession->getAuthSessionId());
    }

    public function testStorageLayoutAfterLogIn(): void
    {
        $this->authSession->logIn(authSessionId: 5);

        $this->assertSame(
            ['yuf' => ['auth' => ['isLoggedIn' => true, 'authSessionId' => 5]]],
            $this->storage->all(),
        );
    }

    public function testLogInRegeneratesTheSessionId(): void
    {
        $idBefore = $this->session->getId();

        $this->authSession->logIn(authSessionId: 5);

        $this->assertNotSame($idBefore, $this->session->getId());
        $this->assertSame('array-session-1', $this->session->getId());
    }

    public function testIsNotLoggedInWithoutLogIn(): void
    {
        $this->assertFalse($this->authSession->isLoggedIn());
    }

    public function testReadingTheLoginStateDoesNotWriteIntoTheSession(): void
    {
        $this->authSession->isLoggedIn();

        $this->assertSame([], $this->storage->all());
    }

    public function testAuthSessionIdWithoutLogInThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('No user is logged in: there is no auth session ID.');

        $this->authSession->getAuthSessionId();
    }

    public function testLogOutClearsUserDataAndRegeneratesTheSessionId(): void
    {
        $this->authSession->logIn(authSessionId: 5);
        $this->storage->set(key: 'yuf', value: [
            'handler' => ['sessionCreated' => 1_790_000_000, 'preferredLanguage' => 'de'],
            'auth' => ['isLoggedIn' => true, 'authSessionId' => 5],
            'csrf' => ['token' => 'token'],
        ]);
        $this->session->set(key: 'sess_breadcrumb', value: ['home' => ['title' => 'Home', 'link' => 'home']]);

        $this->authSession->logOut();

        $this->assertSame(
            ['yuf' => ['handler' => ['sessionCreated' => 1_790_000_000, 'preferredLanguage' => 'de']]],
            $this->storage->all(),
        );
        $this->assertFalse($this->authSession->isLoggedIn());
        $this->assertSame('array-session-2', $this->session->getId());
    }

    public function testGetAuthSessionIdAfterLogOutThrows(): void
    {
        $this->authSession->logIn(authSessionId: 5);
        $this->authSession->logOut();

        $this->expectException(LogicException::class);

        $this->authSession->getAuthSessionId();
    }

    public function testLogOutWithoutLogInKeepsTheSession(): void
    {
        $this->session->set(key: 'sess_breadcrumb', value: ['home' => ['title' => 'Home', 'link' => 'home']]);

        $this->authSession->logOut();

        $this->assertSame(
            ['sess_breadcrumb' => ['home' => ['title' => 'Home', 'link' => 'home']]],
            $this->storage->all(),
        );
        $this->assertSame('array-session', $this->session->getId());
    }

    public function testLoginStateWithoutAuthSessionIdIsLoggedOutAndClearsTheUserData(): void
    {
        $this->storage->set(key: 'yuf', value: ['auth' => ['isLoggedIn' => true]]);
        $this->session->set(key: 'cart', value: 'full');

        $this->assertFalse($this->authSession->isLoggedIn());
        $this->assertSame([], $this->storage->all());
        $this->assertSame('array-session-1', $this->session->getId());
    }

    public function testIndicatorThatIsNoBooleanMeansLoggedOut(): void
    {
        $this->storage->set(key: 'yuf', value: ['auth' => ['isLoggedIn' => 'yes', 'authSessionId' => 5]]);

        $this->assertFalse($this->authSession->isLoggedIn());
    }

    public function testAuthDataThatIsNoArrayMeansLoggedOut(): void
    {
        $this->storage->set(key: 'yuf', value: ['auth' => 'text']);

        $this->assertFalse($this->authSession->isLoggedIn());
    }

    public function testStoredIdOfALoggedOutStateIsNotReadable(): void
    {
        $this->storage->set(key: 'yuf', value: ['auth' => ['isLoggedIn' => false, 'authSessionId' => 5]]);

        $this->assertFalse($this->authSession->isLoggedIn());
        $this->expectException(LogicException::class);

        $this->authSession->getAuthSessionId();
    }

    public function testSecondLogInReplacesTheAuthSessionId(): void
    {
        $this->authSession->logIn(authSessionId: 5);

        $this->authSession->logIn(authSessionId: 6);

        $this->assertTrue($this->authSession->isLoggedIn());
        $this->assertSame(6, $this->authSession->getAuthSessionId());
    }

    public function testLogOutTwiceRegeneratesTheSessionIdOncePerLogOut(): void
    {
        $this->authSession->logIn(authSessionId: 5);

        $this->authSession->logOut();
        $this->authSession->logOut();

        $this->assertFalse($this->authSession->isLoggedIn());
        $this->assertSame('array-session-2', $this->session->getId());
    }

    public function testLogInAfterLogOutIsPossible(): void
    {
        $this->authSession->logIn(authSessionId: 5);
        $this->authSession->logOut();

        $this->authSession->logIn(authSessionId: 7);

        $this->assertTrue($this->authSession->isLoggedIn());
        $this->assertSame(7, $this->authSession->getAuthSessionId());
    }

    public function testLogOutRemovesTheStateOfTablesAndSearch(): void
    {
        $this->authSession->logIn(authSessionId: 5);
        $this->storage->set(key: 'yuf', value: [
            'auth' => ['isLoggedIn' => true, 'authSessionId' => 5],
            'tables' => ['items' => ['sortColumn' => 'name']],
            'search' => ['users' => ['status' => 'active']],
        ]);

        $this->authSession->logOut();

        $this->assertSame([], $this->session->getSection(section: SessionSectionEnum::TABLES));
        $this->assertSame([], $this->session->getSection(section: SessionSectionEnum::SEARCH));
    }

    public function testGetSessionIdIsTheIdOfTheSession(): void
    {
        $this->assertSame('array-session', $this->authSession->getSessionId());
    }
}
