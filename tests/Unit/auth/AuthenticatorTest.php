<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\auth\Authenticator;
use actra\yuf\auth\AuthResultEnum;
use actra\yuf\auth\AuthSession;
use actra\yuf\session\AbstractSessionHandler;
use actra\yuf\tests\Double\auth\RecordingAuthenticator;
use actra\yuf\tests\Double\auth\TestAuthUser;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Characterization of the session behaviour before the redesign (docs/session/plan.md, step 1): what a login writes
 * into the session (checked through `AuthSession`) and the single-instance guards of `Authenticator` and `AuthUser`.
 *
 * `Authenticator` and the session handler are singletons without a reset method: like in `AuthSessionTest`, they are
 * reset through reflection after each test (removed in step 2).
 */
final class AuthenticatorTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        $_SESSION = [];
        new ReflectionProperty(class: AbstractSessionHandler::class, property: 'abstractSessionHandler')->setValue(
            null,
            new AuthenticatorTestSessionHandler(),
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        TestAuthUser::release();
        new ReflectionProperty(class: Authenticator::class, property: 'instance')->setValue(null, null);
        new ReflectionProperty(class: AbstractSessionHandler::class, property: 'abstractSessionHandler')->setValue(
            null,
            null,
        );
        unset($_SESSION);
    }

    public function testLogsAnUnknownUserWithNamedArguments(): void
    {
        $authenticator = new RecordingAuthenticator(
            httpRequest: HttpRequestFactory::create(remoteAddress: '203.0.113.5'),
            authUser: null,
        );

        $this->assertFalse($authenticator->passwordLogin(userName: 'nobody', inputPassword: 'test'));

        $this->assertSame(
            [
                [
                    'userId' => null,
                    'sessionId' => '',
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
            authUser: $authUser,
        );

        $this->assertTrue($authenticator->passwordLogin(userName: 'user', inputPassword: 'test'));

        $this->assertSame(
            [
                [
                    'userId' => 1,
                    'sessionId' => '',
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
            authUser: TestAuthUser::create(accessRights: [AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN]),
        );

        $authenticator->passwordLogin(userName: 'user', inputPassword: 'test');

        $this->assertTrue(AuthSession::isLoggedIn());
        $this->assertSame(0, AuthSession::getAuthSessionId());
    }

    public function testFailedLoginLeavesTheSessionLoggedOut(): void
    {
        $authenticator = new RecordingAuthenticator(httpRequest: HttpRequestFactory::create(), authUser: null);

        $authenticator->passwordLogin(userName: 'nobody', inputPassword: 'test');

        $this->assertFalse(AuthSession::isLoggedIn());
    }

    public function testLoginOfAnAlreadyLoggedInUserThrows(): void
    {
        AuthSession::logIn(authSessionId: 5);
        $authenticator = new RecordingAuthenticator(httpRequest: HttpRequestFactory::create(), authUser: null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('It is not allowed to log in, if user is already logged in.');

        $authenticator->passwordLogin(userName: 'user', inputPassword: 'test');
    }

    public function testLoginTwiceWithTheSameAuthenticatorThrows(): void
    {
        $authenticator = new RecordingAuthenticator(httpRequest: HttpRequestFactory::create(), authUser: null);
        $authenticator->passwordLogin(userName: 'nobody', inputPassword: 'test');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('It is not allowed to execute this method multiple times.');

        $authenticator->passwordLogin(userName: 'nobody', inputPassword: 'test');
    }

    /**
     * Removed in step 2 together with the single-instance guard of `Authenticator`.
     */
    public function testSecondAuthenticatorThrows(): void
    {
        new RecordingAuthenticator(httpRequest: HttpRequestFactory::create(), authUser: null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('There can only be one Authenticator instance.');

        new RecordingAuthenticator(httpRequest: HttpRequestFactory::create(), authUser: null);
    }

    /**
     * Removed in step 2 together with the single-instance guard of `AuthUser`.
     */
    public function testSecondAuthUserThrows(): void
    {
        TestAuthUser::create(accessRights: []);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('There can only be one AuthUser instance.');

        TestAuthUser::create(accessRights: []);
    }
}

final class AuthenticatorTestSessionHandler extends AbstractSessionHandler
{
    /**
     * Does not start a session.
     */
    public function __construct() {} // @phpstan-ignore constructor.missingParentCall (the parent starts a session)

    #[Override]
    protected function executePreStartActions(): void {}
}
