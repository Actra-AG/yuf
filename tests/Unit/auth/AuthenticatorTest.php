<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

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
use PHPUnit\Framework\Attributes\DataProvider;
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
        $authUser = TestAuthUser::create(accessRights: []);
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
            authUser: TestAuthUser::create(accessRights: []),
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
        $authUser = TestAuthUser::create(accessRights: []);
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
            accessRights: [],
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
            accessRights: [],
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
            accessRights: [],
            wrongPasswordAttempts: 2,
        );

        $authenticator = $this->createAuthenticator(authUser: $authUser);

        $this->assertTrue($authenticator->passwordLogin(userName: 'user', inputPassword: 'test'));
    }

    public function testInactiveUserIsRejectedEvenWithTheRightPassword(): void
    {
        $authUser = TestAuthUser::create(
            accessRights: [],
            isActive: false,
        );
        $authenticator = $this->createAuthenticator(authUser: $authUser);

        $this->assertFalse($authenticator->passwordLogin(userName: 'user', inputPassword: 'test'));

        $this->assertSame(AuthResultEnum::ERROR_INACTIVE, $authenticator->authResult);
        $this->assertSame(0, $authUser->increaseCalls);
    }

    public function testUserWithoutAnyRightsCanLogInWithTheirPassword(): void
    {
        $authenticator = $this->createAuthenticator(authUser: TestAuthUser::create(accessRights: []));

        $this->assertTrue($authenticator->passwordLogin(userName: 'user', inputPassword: 'test'));

        $this->assertSame(AuthResultEnum::SUCCESSFUL_PASSWORD_LOGIN, $authenticator->authResult);
    }

    public function testUserWithoutPasswordIsRejectedForAPasswordLogin(): void
    {
        $authUser = TestAuthUser::create(accessRights: ['other'], hasPassword: false);
        $authenticator = $this->createAuthenticator(authUser: $authUser);

        $this->assertFalse($authenticator->passwordLogin(userName: 'user', inputPassword: 'test'));

        $this->assertSame(AuthResultEnum::ERROR_NO_PASSWORD_LOGIN_ACTIVE, $authenticator->authResult);
        $this->assertFalse($this->authSession->isLoggedIn());
        $this->assertSame(
            AuthResultEnum::ERROR_NO_PASSWORD_LOGIN_ACTIVE,
            $this->getFirstLoggedResult(authenticator: $authenticator),
        );
    }

    public function testUserWithoutPasswordCanLogInWithoutPassword(): void
    {
        $authUser = TestAuthUser::create(accessRights: [], hasPassword: false);
        $authenticator = $this->createAuthenticator(authUser: $authUser);

        $this->assertTrue($authenticator->otpLogin(userName: 'user'));

        $this->assertSame(1, $authUser->confirmCalls);
        $this->assertNull($authUser->storedPassword);
        $this->assertNull($authUser->password);
    }

    public function testIpAddressOutsideTheWhitelistIsRejected(): void
    {
        $authUser = TestAuthUser::create(
            accessRights: [],
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
            accessRights: [],
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
            authUser: TestAuthUser::create(accessRights: []),
            credentialsAreValid: false,
        );

        $this->assertFalse($authenticator->passwordLogin(userName: 'user', inputPassword: 'test'));

        $this->assertSame(
            AuthResultEnum::ERROR_NO_PASSWORD,
            $this->getFirstLoggedResult(authenticator: $authenticator),
        );
    }

    public function testUnknownUserWithoutPasswordCheckIsLogged(): void
    {
        $authenticator = $this->createAuthenticator(authUser: null);

        $this->assertFalse($authenticator->otpLogin(userName: 'nobody'));

        $this->assertSame(AuthResultEnum::ERROR_UNKNOWN_USER_NAME, $authenticator->authResult);
        $this->assertSame(
            [AuthResultEnum::ERROR_UNKNOWN_USER_NAME],
            array_column(array: $authenticator->loggedResults, column_key: 'authResult'),
        );
        $this->assertFalse($this->authSession->isLoggedIn());
    }

    public function testRejectionsAreLoggedWithTheIdOfTheUser(): void
    {
        $authUser = TestAuthUser::create(
            accessRights: [],
            isActive: false,
        );
        $authenticator = $this->createAuthenticator(authUser: $authUser);

        $authenticator->passwordLogin(userName: 'user', inputPassword: 'test');

        $this->assertSame(
            [
                [
                    'userId' => 1,
                    'sessionId' => 'array-session',
                    'ip' => '203.0.113.5',
                    'userName' => 'user',
                    'authResult' => AuthResultEnum::ERROR_INACTIVE,
                ],
            ],
            $authenticator->loggedResults,
        );
    }

    public function testIpWhitelistIsCheckedBeforeTheCheckOfTheProject(): void
    {
        $authUser = TestAuthUser::create(accessRights: [], ipWhitelist: ['198.51.100.0/24']);
        $authenticator = $this->createAuthenticator(authUser: $authUser, credentialsAreValid: false);

        $this->assertFalse($authenticator->otpLogin(userName: 'user'));

        $this->assertSame(AuthResultEnum::ERROR_IP_NOT_ALLOWED, $authenticator->authResult);
    }

    public function testCheckOfTheProjectIsDoneBeforeTheStateOfTheUser(): void
    {
        $authUser = TestAuthUser::create(accessRights: [], isActive: false, wrongPasswordAttempts: 3);
        $authenticator = $this->createAuthenticator(authUser: $authUser, credentialsAreValid: false);

        $this->assertFalse($authenticator->otpLogin(userName: 'user'));

        $this->assertSame(AuthResultEnum::ERROR_NO_PASSWORD, $authenticator->authResult);
    }

    public function testInactiveIsReportedBeforeOutTried(): void
    {
        $authUser = TestAuthUser::create(accessRights: [], isActive: false, wrongPasswordAttempts: 3);
        $authenticator = $this->createAuthenticator(authUser: $authUser);

        $this->assertFalse($authenticator->otpLogin(userName: 'user'));

        $this->assertSame(AuthResultEnum::ERROR_INACTIVE, $authenticator->authResult);
    }

    public function testUserWithoutPasswordIsNotCountedAsWrongAttemptAndNotRehashed(): void
    {
        $authUser = TestAuthUser::create(accessRights: [], wrongPasswordAttempts: 1, hasPassword: false);
        $authenticator = $this->createAuthenticator(authUser: $authUser);

        $this->assertFalse($authenticator->passwordLogin(userName: 'user', inputPassword: 'wrong'));

        $this->assertSame(AuthResultEnum::ERROR_NO_PASSWORD_LOGIN_ACTIVE, $authenticator->authResult);
        $this->assertSame(0, $authUser->increaseCalls);
        $this->assertSame(1, $authUser->wrongPasswordAttempts);
        $this->assertNull($authUser->storedPassword);
        $this->assertNull($authUser->password);
    }

    public function testRejectionsDoNotConfirmALogin(): void
    {
        $authUser = TestAuthUser::create(accessRights: [], isActive: false);
        $authenticator = $this->createAuthenticator(authUser: $authUser);

        $authenticator->otpLogin(userName: 'user');

        $this->assertSame(0, $authUser->confirmCalls);
    }

    public function testLastWrongAttemptLocksTheUserOut(): void
    {
        $authUser = TestAuthUser::create(
            accessRights: [],
            wrongPasswordAttempts: 2,
        );

        $this->createAuthenticator(authUser: $authUser)->passwordLogin(userName: 'user', inputPassword: 'wrong');
        $second = $this->createAuthenticator(authUser: $authUser);

        $this->assertFalse($second->passwordLogin(userName: 'user', inputPassword: 'test'));
        $this->assertSame(AuthResultEnum::ERROR_OUT_TRIED, $second->authResult);
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
            accessRights: [],
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
            accessRights: [],
            password: $legacy,
        );

        $authenticator = $this->createAuthenticator(authUser: $authUser);

        $this->assertFalse($authenticator->passwordLogin(userName: 'user', inputPassword: 'x'));

        $this->assertNull($authUser->storedPassword);
        $this->assertSame($legacy, $authUser->password);
    }

    public function testCurrentPasswordIsNotRehashed(): void
    {
        $authUser = TestAuthUser::create(accessRights: []);

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
            authUser: TestAuthUser::create(accessRights: []),
        );

        $authenticator->passwordLogin(userName: 'user', inputPassword: 'test');

        $this->assertSame('array-session-1', $this->authSession->getSessionId());
        $this->assertSame('array-session', array_first(array: $authenticator->loggedResults)['sessionId'] ?? null);
    }

    public function testFailedLoginKeepsTheSessionId(): void
    {
        $authenticator = $this->createAuthenticator(
            authUser: TestAuthUser::create(accessRights: []),
        );

        $authenticator->passwordLogin(userName: 'user', inputPassword: 'wrong');

        $this->assertSame('array-session', $this->authSession->getSessionId());
    }

    public function testVerifyPasswordReturnsTheUserAndDoesNotLogIn(): void
    {
        $authUser = TestAuthUser::create(accessRights: []);
        $authenticator = $this->createAuthenticator(authUser: $authUser);

        $result = $authenticator->verifyPassword(userName: 'user', inputPassword: 'test');

        $this->assertSame($authUser, $result);
        $this->assertFalse($this->authSession->isLoggedIn());
        $this->assertSame('array-session', $this->authSession->getSessionId());
        $this->assertSame([], $authenticator->loggedResults);
        $this->assertSame(AuthResultEnum::UNDEFINED, $authenticator->authResult);
        $this->assertSame(0, $authUser->confirmCalls);
    }

    public function testVerifyPasswordCountsAndLogsAWrongPassword(): void
    {
        $authUser = TestAuthUser::create(accessRights: []);
        $authenticator = $this->createAuthenticator(authUser: $authUser);

        $result = $authenticator->verifyPassword(userName: 'user', inputPassword: 'wrong');

        $this->assertSame(AuthResultEnum::ERROR_WRONG_PASSWORD, $result);
        $this->assertSame(AuthResultEnum::ERROR_WRONG_PASSWORD, $authenticator->authResult);
        $this->assertSame(1, $authUser->increaseCalls);
        $this->assertSame(
            AuthResultEnum::ERROR_WRONG_PASSWORD,
            $this->getFirstLoggedResult(authenticator: $authenticator),
        );
    }

    public function testVerifyPasswordRejectsAnUnknownUserWithALog(): void
    {
        $authenticator = $this->createAuthenticator(authUser: null);

        $this->assertSame(
            AuthResultEnum::ERROR_UNKNOWN_USER_NAME,
            $authenticator->verifyPassword(userName: 'nobody', inputPassword: 'test'),
        );
        $this->assertSame(
            AuthResultEnum::ERROR_UNKNOWN_USER_NAME,
            $this->getFirstLoggedResult(authenticator: $authenticator),
        );
    }

    public function testVerifyPasswordRehashesALegacyPassword(): void
    {
        $legacy = new Password(salt: 'abcdefghijklmnop', hash: hash(algo: 'sha256', data: 'abcdefghijklmnoptest'));
        $authUser = TestAuthUser::create(
            accessRights: [],
            password: $legacy,
        );

        $this->createAuthenticator(authUser: $authUser)->verifyPassword(userName: 'user', inputPassword: 'test');

        $this->assertNotNull($authUser->storedPassword);
        $this->assertTrue($authUser->storedPassword->isValid(rawPassword: 'test'));
    }

    public function testVerifyPasswordAppliesTheSameChecksAsALogin(): void
    {
        $withoutPassword = $this->createAuthenticator(
            authUser: TestAuthUser::create(accessRights: ['other'], hasPassword: false),
        );
        $lockedOut = $this->createAuthenticator(
            authUser: TestAuthUser::create(
                accessRights: [],
                wrongPasswordAttempts: 3,
            ),
        );

        $this->assertSame(
            AuthResultEnum::ERROR_NO_PASSWORD_LOGIN_ACTIVE,
            $withoutPassword->verifyPassword(userName: 'user', inputPassword: 'test'),
        );
        $this->assertSame(
            AuthResultEnum::ERROR_OUT_TRIED,
            $lockedOut->verifyPassword(userName: 'user', inputPassword: 'test'),
        );
    }

    public function testVerifyCredentialsWithoutPasswordChecksTheStateOnly(): void
    {
        $authUser = TestAuthUser::create(accessRights: []);
        $authenticator = $this->createAuthenticator(authUser: $authUser);

        $this->assertSame(
            $authUser,
            $authenticator->verify(authMethod: AuthMethodEnum::OTP, userName: 'user', password: null),
        );
        $this->assertFalse($this->authSession->isLoggedIn());
    }

    public function testVerifyTwiceAfterARejectionThrows(): void
    {
        $authenticator = $this->createAuthenticator(authUser: null);
        $authenticator->verifyPassword(userName: 'nobody', inputPassword: 'test');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('It is not allowed to execute this method multiple times.');

        $authenticator->verifyPassword(userName: 'nobody', inputPassword: 'test');
    }

    public function testVerifyWhileLoggedInThrows(): void
    {
        $this->authSession->logIn(authSessionId: 5);
        $authenticator = $this->createAuthenticator(authUser: null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('It is not allowed to log in, if user is already logged in.');

        $authenticator->verifyPassword(userName: 'user', inputPassword: 'test');
    }

    public function testPrecheckReturnsTheUserWithoutPasswordCountingOrLog(): void
    {
        $authUser = TestAuthUser::create(accessRights: []);
        $authenticator = $this->createAuthenticator(authUser: $authUser);

        $this->assertSame($authUser, $authenticator->precheck(userName: 'user'));

        $this->assertSame([], $authenticator->loggedResults);
        $this->assertSame(AuthResultEnum::UNDEFINED, $authenticator->authResult);
        $this->assertSame(0, $authUser->increaseCalls);
        $this->assertFalse($this->authSession->isLoggedIn());
    }

    /**
     * @return iterable<string, array{0: ?TestAuthUser, 1: AuthResultEnum, 2: bool}>
     */
    public static function precheckRejections(): iterable
    {
        yield 'unknown user' => [null, AuthResultEnum::ERROR_UNKNOWN_USER_NAME, true];
        yield 'ip' => [
            TestAuthUser::create(accessRights: [], ipWhitelist: ['198.51.100.0/24']),
            AuthResultEnum::ERROR_IP_NOT_ALLOWED,
            true,
        ];
        yield 'project check' => [TestAuthUser::create(accessRights: []), AuthResultEnum::ERROR_NO_PASSWORD, false];
        yield 'inactive' => [
            TestAuthUser::create(accessRights: [], isActive: false),
            AuthResultEnum::ERROR_INACTIVE,
            true,
        ];
        yield 'out tried' => [
            TestAuthUser::create(accessRights: [], wrongPasswordAttempts: 3),
            AuthResultEnum::ERROR_OUT_TRIED,
            true,
        ];
    }

    #[DataProvider('precheckRejections')]
    public function testPrecheckRejectsAndLogsLikeALogin(
        ?TestAuthUser $authUser,
        AuthResultEnum $expected,
        bool $credentialsAreValid,
    ): void {
        $authenticator = $this->createAuthenticator(authUser: $authUser, credentialsAreValid: $credentialsAreValid);

        $this->assertSame($expected, $authenticator->precheck(userName: 'user'));

        $this->assertSame($expected, $authenticator->authResult);
        $this->assertSame($expected, $this->getFirstLoggedResult(authenticator: $authenticator));
        $this->assertSame(0, $authUser === null ? 0 : $authUser->increaseCalls);
    }

    public function testPrecheckTwiceAfterARejectionThrows(): void
    {
        $authenticator = $this->createAuthenticator(authUser: null);
        $authenticator->precheck(userName: 'nobody');

        $this->expectException(LogicException::class);

        $authenticator->precheck(userName: 'nobody');
    }

    public function testLogInVerifiedUserLogsInAndLogsTheSuccess(): void
    {
        $authUser = TestAuthUser::create(accessRights: [], wrongPasswordAttempts: 2);
        $authenticator = $this->createAuthenticator(authUser: null);

        $this->assertTrue(
            $authenticator->loginVerified(authMethod: AuthMethodEnum::OTP, authUser: $authUser, userName: 'user'),
        );

        $this->assertTrue($this->authSession->isLoggedIn());
        $this->assertSame(AuthResultEnum::SUCCESSFUL_OTP_LOGIN, $authenticator->authResult);
        $this->assertSame(0, $authUser->wrongPasswordAttempts);
        $this->assertSame(1, $authUser->confirmCalls);
        $this->assertSame(
            [
                [
                    'userId' => 1,
                    'sessionId' => 'array-session',
                    'ip' => '203.0.113.5',
                    'userName' => 'user',
                    'authResult' => AuthResultEnum::SUCCESSFUL_OTP_LOGIN,
                ],
            ],
            $authenticator->loggedResults,
        );
        $this->assertSame('array-session-1', $this->authSession->getSessionId());
    }

    public function testTwoFactorFlowVerifiesInOneRequestAndLogsInInTheNext(): void
    {
        $authUser = TestAuthUser::create(accessRights: []);
        $first = $this->createAuthenticator(authUser: $authUser);
        $verified = $first->verifyPassword(userName: 'user', inputPassword: 'test');
        $this->assertSame($authUser, $verified);
        $this->assertFalse($this->authSession->isLoggedIn());

        $second = $this->createAuthenticator(authUser: null);

        $this->assertTrue(
            $second->loginVerified(authMethod: AuthMethodEnum::PASSWORD, authUser: $authUser, userName: 'user'),
        );
        $this->assertTrue($this->authSession->isLoggedIn());
        $this->assertSame(AuthResultEnum::SUCCESSFUL_PASSWORD_LOGIN, $second->authResult);
    }

    public function testLogInVerifiedUserChecksTheStateAgain(): void
    {
        $authUser = TestAuthUser::create(accessRights: [], isActive: false);
        $authenticator = $this->createAuthenticator(authUser: null);

        $this->assertFalse(
            $authenticator->loginVerified(authMethod: AuthMethodEnum::OTP, authUser: $authUser, userName: 'user'),
        );

        $this->assertFalse($this->authSession->isLoggedIn());
        $this->assertSame(AuthResultEnum::ERROR_INACTIVE, $authenticator->authResult);
        $this->assertSame(
            AuthResultEnum::ERROR_INACTIVE,
            $this->getFirstLoggedResult(authenticator: $authenticator),
        );
        $this->assertSame(0, $authUser->confirmCalls);
    }

    public function testLogInVerifiedUserRefusesALockedOutUserAndAnotherIpAddress(): void
    {
        $lockedOut = TestAuthUser::create(accessRights: [], wrongPasswordAttempts: 3);
        $otherIp = TestAuthUser::create(accessRights: [], ipWhitelist: ['198.51.100.0/24']);
        $first = $this->createAuthenticator(authUser: null);
        $second = $this->createAuthenticator(authUser: null);

        $this->assertFalse(
            $first->loginVerified(authMethod: AuthMethodEnum::OTP, authUser: $lockedOut, userName: 'user'),
        );
        $this->assertFalse(
            $second->loginVerified(authMethod: AuthMethodEnum::OTP, authUser: $otherIp, userName: 'user'),
        );

        $this->assertSame(AuthResultEnum::ERROR_OUT_TRIED, $first->authResult);
        $this->assertSame(AuthResultEnum::ERROR_IP_NOT_ALLOWED, $second->authResult);
    }

    public function testLogInVerifiedUserTwiceThrows(): void
    {
        $authUser = TestAuthUser::create(accessRights: []);
        $authenticator = $this->createAuthenticator(authUser: null);
        $authenticator->loginVerified(authMethod: AuthMethodEnum::OTP, authUser: $authUser, userName: 'user');
        $this->authSession->logOut();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('It is not allowed to execute this method multiple times.');

        $authenticator->loginVerified(authMethod: AuthMethodEnum::OTP, authUser: $authUser, userName: 'user');
    }

    public function testLogInVerifiedUserWhileLoggedInThrows(): void
    {
        $this->authSession->logIn(authSessionId: 5);
        $authenticator = $this->createAuthenticator(authUser: null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('It is not allowed to log in, if user is already logged in.');

        $authenticator->loginVerified(
            authMethod: AuthMethodEnum::OTP,
            authUser: TestAuthUser::create(accessRights: []),
            userName: 'user',
        );
    }
}
