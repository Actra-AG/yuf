<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\auth\AuthResultEnum;
use actra\yuf\auth\AuthSession;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\tests\Double\auth\RecordingAuthenticator;
use actra\yuf\tests\Double\auth\TestAuthUser;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * What a login writes into the session (checked through `AuthSession`) and how the `Authenticator` handles the login
 * state. Several authenticators and users per request are possible (no static guards).
 */
final class AuthenticatorTest extends TestCase
{
    private AuthSession $authSession;

    #[Override]
    protected function setUp(): void
    {
        $this->authSession = new AuthSession(session: new Session(storage: new ArraySessionStorage()));
    }

    public function testLogsAnUnknownUserWithNamedArguments(): void
    {
        $authenticator = new RecordingAuthenticator(
            httpRequest: HttpRequestFactory::create(remoteAddress: '203.0.113.5'),
            authSession: $this->authSession,
            authUser: null,
        );

        $this->assertFalse($authenticator->passwordLogin(userName: 'nobody', inputPassword: 'test'));

        $this->assertSame(
            [
                [
                    'userId' => null,
                    'sessionId' => 'array-session',
                    'ip' => '203.0.113.5',
                    'userName' => 'nobody',
                    'authResult' => AuthResultEnum::ERROR_UNKNOWN_USER_NAME,
                ],
            ],
            $authenticator->loggedResults,
        );
    }

    public function testLogsASuccessfulPasswordLoginAndLogsTheUserIn(): void
    {
        $authUser = TestAuthUser::create(accessRights: [AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN]);
        $authenticator = new RecordingAuthenticator(
            httpRequest: HttpRequestFactory::create(remoteAddress: '203.0.113.5'),
            authSession: $this->authSession,
            authUser: $authUser,
        );

        $this->assertTrue($authenticator->passwordLogin(userName: 'user', inputPassword: 'test'));

        $this->assertSame(
            [
                [
                    'userId' => 1,
                    'sessionId' => 'array-session',
                    'ip' => '203.0.113.5',
                    'userName' => 'user',
                    'authResult' => AuthResultEnum::SUCCESSFUL_PASSWORD_LOGIN,
                ],
            ],
            $authenticator->loggedResults,
        );
        $this->assertSame(AuthResultEnum::SUCCESSFUL_PASSWORD_LOGIN, $authenticator->authResult);
        $this->assertSame(1, $authUser->id);
    }

    public function testSuccessfulLoginLogsTheUserInTheSession(): void
    {
        $authenticator = new RecordingAuthenticator(
            httpRequest: HttpRequestFactory::create(),
            authSession: $this->authSession,
            authUser: TestAuthUser::create(accessRights: [AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN]),
        );

        $authenticator->passwordLogin(userName: 'user', inputPassword: 'test');

        $this->assertTrue($this->authSession->isLoggedIn());
        $this->assertSame(0, $this->authSession->getAuthSessionId());
    }

    public function testFailedLoginLeavesTheSessionLoggedOut(): void
    {
        $authenticator = new RecordingAuthenticator(
            httpRequest: HttpRequestFactory::create(),
            authSession: $this->authSession,
            authUser: null,
        );

        $authenticator->passwordLogin(userName: 'nobody', inputPassword: 'test');

        $this->assertFalse($this->authSession->isLoggedIn());
    }

    public function testLoginOfAnAlreadyLoggedInUserThrows(): void
    {
        $this->authSession->logIn(authSessionId: 5);
        $authenticator = new RecordingAuthenticator(
            httpRequest: HttpRequestFactory::create(),
            authSession: $this->authSession,
            authUser: null,
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('It is not allowed to log in, if user is already logged in.');

        $authenticator->passwordLogin(userName: 'user', inputPassword: 'test');
    }

    public function testLoginTwiceWithTheSameAuthenticatorThrows(): void
    {
        $authenticator = new RecordingAuthenticator(
            httpRequest: HttpRequestFactory::create(),
            authSession: $this->authSession,
            authUser: null,
        );
        $authenticator->passwordLogin(userName: 'nobody', inputPassword: 'test');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('It is not allowed to execute this method multiple times.');

        $authenticator->passwordLogin(userName: 'nobody', inputPassword: 'test');
    }

    public function testSeveralAuthenticatorsAndUsersAreAllowed(): void
    {
        $first = new RecordingAuthenticator(
            httpRequest: HttpRequestFactory::create(),
            authSession: $this->authSession,
            authUser: TestAuthUser::create(accessRights: []),
        );
        $second = new RecordingAuthenticator(
            httpRequest: HttpRequestFactory::create(),
            authSession: $this->authSession,
            authUser: TestAuthUser::create(accessRights: []),
        );

        $this->assertNotSame($first, $second);
        $this->assertFalse($first->passwordLogin(userName: 'user', inputPassword: 'wrong'));
        $this->assertFalse($second->passwordLogin(userName: 'user', inputPassword: 'wrong'));
    }
}
