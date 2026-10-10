<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\tests\Double\auth\AttemptCounter;
use actra\yuf\tests\Double\auth\TestAuthUser;
use PHPUnit\Framework\TestCase;

final class AuthUserTest extends TestCase
{
    public function testActiveUserHasTheRightsOfItsCollection(): void
    {
        $authUser = TestAuthUser::create(accessRights: ['read', 'write']);

        $this->assertTrue(
            $authUser->hasOneOfRights(
                accessRightCollection: AccessRightCollection::createFromStringArray(input: ['write']),
            ),
        );
        $this->assertFalse(
            $authUser->hasOneOfRights(
                accessRightCollection: AccessRightCollection::createFromStringArray(input: ['admin']),
            ),
        );
    }

    public function testInactiveUserHasNoRights(): void
    {
        $authUser = TestAuthUser::create(accessRights: ['read'], isActive: false);

        $this->assertFalse(
            $authUser->hasOneOfRights(
                accessRightCollection: AccessRightCollection::createFromStringArray(input: ['read']),
            ),
        );
    }

    public function testRegisteredAttemptIsCountedInTheDatabaseAndInTheUser(): void
    {
        $authUser = TestAuthUser::create(accessRights: [], wrongPasswordAttempts: 1);

        $isRegistered = $authUser->registerWrongPasswordAttempt(maxAllowedWrongPasswordAttempts: 3);

        $this->assertTrue($isRegistered);
        $this->assertSame(2, $authUser->wrongPasswordAttempts);
        $this->assertSame(1, $authUser->registerCalls);
    }

    public function testAttemptAtTheLimitIsRefusedAndTheUserIsLocked(): void
    {
        $counter = new AttemptCounter(value: 3);
        // Loaded before a parallel request counted the last attempt
        $authUser = TestAuthUser::create(accessRights: [], wrongPasswordAttempts: 2, counter: $counter);

        $isRegistered = $authUser->registerWrongPasswordAttempt(maxAllowedWrongPasswordAttempts: 3);

        $this->assertFalse($isRegistered);
        $this->assertSame(3, $authUser->wrongPasswordAttempts);
        $this->assertSame(3, $counter->value);
    }

    public function testReleasedAttemptIsGivenBack(): void
    {
        $counter = new AttemptCounter(value: 2);
        $authUser = TestAuthUser::create(accessRights: [], wrongPasswordAttempts: 2, counter: $counter);
        $authUser->registerWrongPasswordAttempt(maxAllowedWrongPasswordAttempts: 3);

        $authUser->releaseWrongPasswordAttempt();

        $this->assertSame(2, $authUser->wrongPasswordAttempts);
        $this->assertSame(2, $counter->value);
    }

    public function testSuccessfulLoginDoesNotResetTheAttempts(): void
    {
        $authUser = TestAuthUser::create(accessRights: [], wrongPasswordAttempts: 2);

        $authUser->confirmSuccessfulLogin();

        $this->assertSame(2, $authUser->wrongPasswordAttempts);
        $this->assertSame(1, $authUser->confirmCalls);
    }

    public function testRehashStoresAndKeepsTheNewPassword(): void
    {
        $authUser = TestAuthUser::create(accessRights: []);
        $oldPassword = $authUser->password;

        $authUser->rehashPassword(rawPassword: 'new secret');

        $this->assertNotSame($oldPassword, $authUser->password);
        $this->assertSame($authUser->password, $authUser->storedPassword);
        $this->assertNotNull($authUser->password);
        $this->assertTrue($authUser->password->isValid(rawPassword: 'new secret'));
        $this->assertSame(0, $authUser->wrongPasswordAttempts);
    }
}
