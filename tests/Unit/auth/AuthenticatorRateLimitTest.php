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
use actra\yuf\auth\LoginRateLimit;
use actra\yuf\auth\Password;
use actra\yuf\clock\FixedClock;
use actra\yuf\security\RateLimit;
use actra\yuf\security\RateLimiter;
use actra\yuf\security\RateLimitFailModeEnum;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\tests\Double\auth\RecordingAuthenticator;
use actra\yuf\tests\Double\auth\TestAuthUser;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\core\RecordingLogger;
use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * The `LoginRateLimit` of the `Authenticator`: counted per client IP address and account name before the user is
 * loaded, the same for existing and unknown accounts.
 */
final class AuthenticatorRateLimitTest extends TestCase
{
    private const string SECRET = '0123456789abcdef0123456789abcdef';

    private string $directory;
    private RecordingLogger $logger;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-login-rate-limit-'
            . bin2hex(string: random_bytes(length: 8));
        $this->logger = new RecordingLogger();
    }

    #[Override]
    protected function tearDown(): void
    {
        if (is_file(filename: $this->directory)) {
            unlink(filename: $this->directory);

            return;
        }
        if (!is_dir(filename: $this->directory)) {
            return;
        }
        $files = scandir(directory: $this->directory);
        foreach ($files === false ? [] : $files as $file) {
            if ($file !== '.' && $file !== '..') {
                unlink(filename: $this->directory . DIRECTORY_SEPARATOR . $file);
            }
        }
        rmdir(directory: $this->directory);
    }

    private function createRateLimit(
        int $clientMaxHits = 10,
        int $accountMaxHits = 2,
        RateLimitFailModeEnum $failMode = RateLimitFailModeEnum::CLOSED,
    ): LoginRateLimit {
        return new LoginRateLimit(
            rateLimiter: new RateLimiter(
                directory: $this->directory,
                secret: AuthenticatorRateLimitTest::SECRET,
                clock: new FixedClock(now: new DateTimeImmutable(datetime: '@1800000000')),
                purgeProbabilityPercent: 0,
            ),
            failMode: $failMode,
            logger: $this->logger,
            clientLimits: [new RateLimit(maxHits: $clientMaxHits, windowSeconds: 900)],
            accountLimits: [new RateLimit(maxHits: $accountMaxHits, windowSeconds: 900)],
        );
    }

    private function createAuthenticator(
        LoginRateLimit $rateLimit,
        ?TestAuthUser $authUser = null,
        string $remoteAddress = '203.0.113.5',
    ): RecordingAuthenticator {
        return new RecordingAuthenticator(
            httpRequest: HttpRequestFactory::create(remoteAddress: $remoteAddress),
            authSession: new AuthSession(session: new Session(storage: new ArraySessionStorage())),
            authUser: $authUser,
            loginRateLimit: $rateLimit,
        );
    }

    private static function userWithPassword(): TestAuthUser
    {
        return TestAuthUser::create(accessRights: [], password: Password::generateNew(rawPassword: 'right password'));
    }

    public function testRequiresLimits(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LoginRateLimit(
            rateLimiter: new RateLimiter(directory: $this->directory, secret: AuthenticatorRateLimitTest::SECRET),
            failMode: RateLimitFailModeEnum::CLOSED,
            logger: $this->logger,
            accountLimits: [],
        );
    }

    public function testDefaultLimits(): void
    {
        $rateLimit = new LoginRateLimit(
            rateLimiter: new RateLimiter(directory: $this->directory, secret: AuthenticatorRateLimitTest::SECRET),
            failMode: RateLimitFailModeEnum::CLOSED,
            logger: $this->logger,
        );

        $this->assertEquals(
            [new RateLimit(maxHits: 20, windowSeconds: 900), new RateLimit(maxHits: 100, windowSeconds: 86400)],
            $rateLimit->clientLimits,
        );
        $this->assertEquals([new RateLimit(maxHits: 10, windowSeconds: 900)], $rateLimit->accountLimits);
    }

    public function testBlockedAttemptIsRejectedAndLoggedWithoutUserLookup(): void
    {
        $rateLimit = $this->createRateLimit(accountMaxHits: 1);
        $this->createAuthenticator(rateLimit: $rateLimit)->passwordLogin(userName: 'ann', inputPassword: 'x');
        $authUser = AuthenticatorRateLimitTest::userWithPassword();
        $authenticator = $this->createAuthenticator(rateLimit: $rateLimit, authUser: $authUser);

        // The right password does not help while the account is blocked
        $this->assertFalse($authenticator->passwordLogin(userName: 'ann', inputPassword: 'right password'));

        $this->assertSame(AuthResultEnum::ERROR_RATE_LIMITED, $authenticator->authResult);
        $this->assertSame(900, $authenticator->retryAfterSeconds);
        $this->assertSame(0, $authenticator->userLookups);
        $this->assertSame(0, $authUser->registerCalls);
        $this->assertSame(
            [
                [
                    'userId' => null,
                    'sessionId' => 'array-session',
                    'ip' => '203.0.113.5',
                    'userName' => 'ann',
                    'authResult' => AuthResultEnum::ERROR_RATE_LIMITED,
                ],
            ],
            $authenticator->loggedResults,
        );
    }

    public function testUnknownAndExistingAccountsAreCountedTheSame(): void
    {
        $rateLimit = $this->createRateLimit(accountMaxHits: 2);
        // One attempt for an unknown account name, one for an existing one with the same name
        $this->createAuthenticator(rateLimit: $rateLimit)->passwordLogin(userName: 'ann', inputPassword: 'x');
        $this->createAuthenticator(rateLimit: $rateLimit, authUser: AuthenticatorRateLimitTest::userWithPassword())
            ->passwordLogin(userName: 'ann', inputPassword: 'x');
        $unknown = $this->createAuthenticator(rateLimit: $rateLimit);

        $unknown->passwordLogin(userName: 'ann', inputPassword: 'x');

        $this->assertSame(AuthResultEnum::ERROR_RATE_LIMITED, $unknown->authResult);
    }

    public function testAccountNameIsCaseInsensitiveAndTrimmed(): void
    {
        $rateLimit = $this->createRateLimit(accountMaxHits: 1);
        $this->createAuthenticator(rateLimit: $rateLimit)->passwordLogin(userName: 'Ann@Example.com', inputPassword: 'x');
        // Another IP address: only the account limit applies
        $authenticator = $this->createAuthenticator(rateLimit: $rateLimit, remoteAddress: '198.51.100.1');

        $authenticator->passwordLogin(userName: ' ann@example.com ', inputPassword: 'x');

        $this->assertSame(AuthResultEnum::ERROR_RATE_LIMITED, $authenticator->authResult);
    }

    public function testClientLimitCoversAllAccountsAndIsCheckedFirst(): void
    {
        $rateLimit = $this->createRateLimit(clientMaxHits: 2, accountMaxHits: 1);
        $this->createAuthenticator(rateLimit: $rateLimit)->passwordLogin(userName: 'a', inputPassword: 'x');
        $this->createAuthenticator(rateLimit: $rateLimit)->passwordLogin(userName: 'b', inputPassword: 'x');
        $blocked = $this->createAuthenticator(rateLimit: $rateLimit);
        $blocked->passwordLogin(userName: 'c', inputPassword: 'x');
        // The blocked client did not use up the account limit of "c"
        $otherClient = $this->createAuthenticator(rateLimit: $rateLimit, remoteAddress: '198.51.100.1');

        $otherClient->passwordLogin(userName: 'c', inputPassword: 'x');

        $this->assertSame(AuthResultEnum::ERROR_RATE_LIMITED, $blocked->authResult);
        $this->assertSame(AuthResultEnum::ERROR_UNKNOWN_USER_NAME, $otherClient->authResult);
    }

    public function testVerifyPasswordAndPrecheckAreCounted(): void
    {
        $rateLimit = $this->createRateLimit(accountMaxHits: 2);
        $this->createAuthenticator(rateLimit: $rateLimit)->verifyPassword(userName: 'ann', inputPassword: 'x');
        $this->createAuthenticator(rateLimit: $rateLimit)->precheck(userName: 'ann');

        $result = $this->createAuthenticator(rateLimit: $rateLimit)->verify(
            authMethod: AuthMethodEnum::OTP,
            userName: 'ann',
            password: null,
        );

        $this->assertSame(AuthResultEnum::ERROR_RATE_LIMITED, $result);
    }

    public function testLoginOfAVerifiedUserIsNotCounted(): void
    {
        $rateLimit = $this->createRateLimit(accountMaxHits: 1);
        $this->createAuthenticator(rateLimit: $rateLimit)->precheck(userName: 'ann');
        $authUser = TestAuthUser::create(accessRights: []);
        $authenticator = $this->createAuthenticator(rateLimit: $rateLimit, authUser: $authUser);

        $this->assertTrue(
            $authenticator->loginVerified(authMethod: AuthMethodEnum::OTP, authUser: $authUser, userName: 'ann'),
        );
    }

    public function testFailClosedBlocksAndLogs(): void
    {
        touch(filename: $this->directory);
        $authenticator = $this->createAuthenticator(
            rateLimit: $this->createRateLimit(failMode: RateLimitFailModeEnum::CLOSED),
            authUser: AuthenticatorRateLimitTest::userWithPassword(),
        );

        $this->assertFalse($authenticator->passwordLogin(userName: 'ann', inputPassword: 'right password'));

        $this->assertSame(AuthResultEnum::ERROR_RATE_LIMITED, $authenticator->authResult);
        $this->assertCount(1, $this->logger->loggedExceptions);
    }

    public function testFailOpenAllowsAndLogs(): void
    {
        touch(filename: $this->directory);
        $authenticator = $this->createAuthenticator(
            rateLimit: $this->createRateLimit(failMode: RateLimitFailModeEnum::OPEN),
            authUser: AuthenticatorRateLimitTest::userWithPassword(),
        );

        $this->assertTrue($authenticator->passwordLogin(userName: 'ann', inputPassword: 'right password'));

        $this->assertCount(1, $this->logger->loggedExceptions);
    }
}
