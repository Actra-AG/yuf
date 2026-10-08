<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\auth\AuthMethodEnum;
use actra\yuf\auth\AuthResultEnum;
use actra\yuf\auth\AuthSession;
use actra\yuf\auth\Password;
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

    private function getFirstLoggedResult(RecordingAuthenticator $authenticator): ?AuthResultEnum
    {
        $first = array_first(array: $authenticator->loggedResults);

        return $first === null ? null : $first['authResult'];
    }

    private function createAuthenticator(
        ?TestAuthUser $authUser,
        string $remoteAddress = '203.0.113.5',
        int $maxAllowedWrongPasswordAttempts = 3,
        bool $credentialsAreValid = true,
    ): RecordingAuthenticator {
        return new RecordingAuthenticator(
            httpRequest: HttpRequestFactory::create(remoteAddress: $remoteAddress),
            authSession: $this->authSession,
            authUser: $authUser,
            maxAllowedWrongPasswordAttempts: $maxAllowedWrongPasswordAttempts,
            credentialsAreValid: $credentialsAreValid,
        );
    }

    public function testWrongPasswordIsCountedAndLogged(): void
    {
        $authUser = TestAuthUser::create(accessRights: [AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN]);
        $authenticator = $this->createAuthenticator(authUser: $authUser);

        $this->assertFalse($authenticator->passwordLogin(userName: 'user', inputPassword: 'wrong'));

        $this->assertSame(AuthResultEnum::ERROR_WRONG_PASSWORD, $authenticator->authResult);
        $this->assertSame(1, $authUser->increaseCalls);
        $this->assertSame(1, $authUser->wrongPasswordAttempts);
        $this->assertSame(0, $authUser->confirmCalls);
        $this->assertFalse($this->authSession->isLoggedIn());
        $this->assertSame(
            AuthResultEnum::ERROR_WRONG_PASSWORD,
            $this->getFirstLoggedResult(authenticator: $authenticator),
        );
    }

    public function testSuccessfulLoginResetsTheWrongPasswordAttempts(): void
    {
        $authUser = TestAuthUser::create(
            accessRights: [AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN],
            wrongPasswordAttempts: 2,
        );
        $authenticator = $this->createAuthenticator(authUser: $authUser);

        $this->assertTrue($authenticator->passwordLogin(userName: 'user', inputPassword: 'test'));

        $this->assertSame(0, $authUser->wrongPasswordAttempts);
        $this->assertSame(1, $authUser->confirmCalls);
    }

    public function testUserWithTooManyWrongAttemptsIsLockedOutEvenWithTheRightPassword(): void
    {
        $authUser = TestAuthUser::create(
            accessRights: [AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN],
            wrongPasswordAttempts: 3,
        );
        $authenticator = $this->createAuthenticator(authUser: $authUser);

        $this->assertFalse($authenticator->passwordLogin(userName: 'user', inputPassword: 'test'));

        $this->assertSame(AuthResultEnum::ERROR_OUT_TRIED, $authenticator->authResult);
        $this->assertSame(0, $authUser->increaseCalls);
        $this->assertFalse($this->authSession->isLoggedIn());
    }

    public function testUserBelowTheLimitCanLogIn(): void
    {
        $authUser = TestAuthUser::create(
            accessRights: [AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN],
            wrongPasswordAttempts: 2,
        );

        $authenticator = $this->createAuthenticator(authUser: $authUser);

        $this->assertTrue($authenticator->passwordLogin(userName: 'user', inputPassword: 'test'));
    }

    public function testInactiveUserIsRejectedEvenWithTheRightPassword(): void
    {
        $authUser = TestAuthUser::create(
            accessRights: [AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN],
            isActive: false,
        );
        $authenticator = $this->createAuthenticator(authUser: $authUser);

        $this->assertFalse($authenticator->passwordLogin(userName: 'user', inputPassword: 'test'));

        $this->assertSame(AuthResultEnum::ERROR_INACTIVE, $authenticator->authResult);
        $this->assertSame(0, $authUser->increaseCalls);
    }

    public function testUserWithoutPasswordLoginRightIsRejected(): void
    {
        $authenticator = $this->createAuthenticator(authUser: TestAuthUser::create(accessRights: ['other']));

        $this->assertFalse($authenticator->passwordLogin(userName: 'user', inputPassword: 'test'));

        $this->assertSame(AuthResultEnum::ERROR_NO_PASSWORD_LOGIN_ACTIVE, $authenticator->authResult);
    }

    public function testIpAddressOutsideTheWhitelistIsRejected(): void
    {
        $authUser = TestAuthUser::create(
            accessRights: [AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN],
            ipWhitelist: ['198.51.100.0/24'],
        );
        $authenticator = $this->createAuthenticator(authUser: $authUser, remoteAddress: '203.0.113.5');

        $this->assertFalse($authenticator->passwordLogin(userName: 'user', inputPassword: 'test'));

        $this->assertSame(AuthResultEnum::ERROR_IP_NOT_ALLOWED, $authenticator->authResult);
        $this->assertSame(0, $authUser->increaseCalls);
    }

    public function testIpAddressInsideTheWhitelistCanLogIn(): void
    {
        $authUser = TestAuthUser::create(
            accessRights: [AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN],
            ipWhitelist: ['198.51.100.0/24'],
        );
        $authenticator = $this->createAuthenticator(authUser: $authUser, remoteAddress: '198.51.100.77');

        $this->assertTrue($authenticator->passwordLogin(userName: 'user', inputPassword: 'test'));
    }

    public function testLoginWithoutPasswordCheckNeedsNoPasswordRight(): void
    {
        $authenticator = $this->createAuthenticator(authUser: TestAuthUser::create(accessRights: []));

        $this->assertTrue($authenticator->otpLogin(userName: 'user'));

        $this->assertSame(AuthResultEnum::SUCCESSFUL_OTP_LOGIN, $authenticator->authResult);
        $this->assertTrue($this->authSession->isLoggedIn());
    }

    public function testLoginWithoutPasswordCheckIsStillLockedOut(): void
    {
        $authenticator = $this->createAuthenticator(
            authUser: TestAuthUser::create(accessRights: [], wrongPasswordAttempts: 3),
        );

        $this->assertFalse($authenticator->otpLogin(userName: 'user'));

        $this->assertSame(AuthResultEnum::ERROR_OUT_TRIED, $authenticator->authResult);
    }

    public function testFailedCredentialCheckOfTheProjectIsLoggedWithItsResult(): void
    {
        $authenticator = $this->createAuthenticator(
            authUser: TestAuthUser::create(accessRights: [AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN]),
            credentialsAreValid: false,
        );

        $this->assertFalse($authenticator->passwordLogin(userName: 'user', inputPassword: 'test'));

        $this->assertSame(
            AuthResultEnum::ERROR_NO_PASSWORD,
            $this->getFirstLoggedResult(authenticator: $authenticator),
        );
    }

    public function testEveryAuthMethodHasItsSuccessResult(): void
    {
        $this->assertSame(AuthResultEnum::SUCCESSFUL_PASSWORD_LOGIN, AuthMethodEnum::PASSWORD->getSuccessAuthResult());
        $this->assertSame(AuthResultEnum::SUCCESSFUL_SSO_LOGIN, AuthMethodEnum::SSO->getSuccessAuthResult());
        $this->assertSame(AuthResultEnum::SUCCESSFUL_OTP_LOGIN, AuthMethodEnum::OTP->getSuccessAuthResult());
        $this->assertSame(
            AuthResultEnum::SUCCESSFUL_MICROSOFT_LOGIN,
            AuthMethodEnum::MICROSOFT->getSuccessAuthResult(),
        );
    }

    public function testLegacyPasswordIsRehashedAfterASuccessfulLogin(): void
    {
        $legacy = new Password(salt: 'abcdefghijklmnop', hash: hash(algo: 'sha256', data: 'abcdefghijklmnoptest'));
        $authUser = TestAuthUser::create(
            accessRights: [AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN],
            password: $legacy,
        );
        $authenticator = $this->createAuthenticator(authUser: $authUser);

        $this->assertTrue($authenticator->passwordLogin(userName: 'user', inputPassword: 'test'));

        $this->assertNotNull($authUser->storedPassword);
        $this->assertFalse($authUser->storedPassword->isLegacy());
        $this->assertTrue($authUser->storedPassword->isValid(rawPassword: 'test'));
        $this->assertSame($authUser->storedPassword, $authUser->password);
    }

    public function testLegacyPasswordIsNotRehashedAfterAWrongPassword(): void
    {
        $legacy = new Password(salt: 'abcdefghijklmnop', hash: hash(algo: 'sha256', data: 'abcdefghijklmnoptest'));
        $authUser = TestAuthUser::create(
            accessRights: [AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN],
            password: $legacy,
        );

        $authenticator = $this->createAuthenticator(authUser: $authUser);

        $this->assertFalse($authenticator->passwordLogin(userName: 'user', inputPassword: 'x'));

        $this->assertNull($authUser->storedPassword);
        $this->assertSame($legacy, $authUser->password);
    }

    public function testCurrentPasswordIsNotRehashed(): void
    {
        $authUser = TestAuthUser::create(accessRights: [AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN]);

        $authenticator = $this->createAuthenticator(authUser: $authUser);

        $this->assertTrue($authenticator->passwordLogin(userName: 'user', inputPassword: 'test'));

        $this->assertNull($authUser->storedPassword);
    }

    public function testLoginWithoutPasswordCheckDoesNotRehash(): void
    {
        $legacy = new Password(salt: 'abcdefghijklmnop', hash: hash(algo: 'sha256', data: 'abcdefghijklmnoptest'));
        $authUser = TestAuthUser::create(accessRights: [], password: $legacy);

        $this->assertTrue($this->createAuthenticator(authUser: $authUser)->otpLogin(userName: 'user'));

        $this->assertNull($authUser->storedPassword);
    }

    public function testSuccessfulLoginGivesTheSessionANewId(): void
    {
        $authenticator = $this->createAuthenticator(
            authUser: TestAuthUser::create(accessRights: [AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN]),
        );

        $authenticator->passwordLogin(userName: 'user', inputPassword: 'test');

        $this->assertSame('array-session-1', $this->authSession->getSessionId());
        $this->assertSame('array-session', array_first(array: $authenticator->loggedResults)['sessionId'] ?? null);
    }

    public function testFailedLoginKeepsTheSessionId(): void
    {
        $authenticator = $this->createAuthenticator(
            authUser: TestAuthUser::create(accessRights: [AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN]),
        );

        $authenticator->passwordLogin(userName: 'user', inputPassword: 'wrong');

        $this->assertSame('array-session', $this->authSession->getSessionId());
    }
}
