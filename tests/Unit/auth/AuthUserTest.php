<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\AccessRightCollection;
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

    public function testWrongPasswordAttemptsArePersistedAndCounted(): void
    {
        $authUser = TestAuthUser::create(accessRights: [], wrongPasswordAttempts: 1);

        $authUser->increaseWrongPasswordAttempts();

        $this->assertSame(2, $authUser->wrongPasswordAttempts);
        $this->assertSame(1, $authUser->increaseCalls);
    }

    public function testSuccessfulLoginResetsTheAttempts(): void
    {
        $authUser = TestAuthUser::create(accessRights: [], wrongPasswordAttempts: 2);

        $authUser->confirmSuccessfulLogin();

        $this->assertSame(0, $authUser->wrongPasswordAttempts);
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
