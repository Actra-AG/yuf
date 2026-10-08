<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\auth;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\auth\AuthUser;
use actra\yuf\auth\Password;
use Override;

final class TestAuthUser extends AuthUser
{
    public int $increaseCalls = 0;
    public int $confirmCalls = 0;
    public ?Password $storedPassword = null;

    /**
     * @param list<string> $accessRights
     * @param list<string> $ipWhitelist
     */
    public static function create(
        array $accessRights,
        bool $isActive = true,
        int $wrongPasswordAttempts = 0,
        array $ipWhitelist = [],
        ?Password $password = null,
    ): TestAuthUser {
        return new TestAuthUser(
            id: 1,
            isActive: $isActive,
            wrongPasswordAttempts: $wrongPasswordAttempts,
            accessRightCollection: AccessRightCollection::createFromStringArray(input: $accessRights),
            password: $password ?? Password::generateNew(rawPassword: 'test'),
            ipWhitelist: $ipWhitelist,
        );
    }

    #[Override]
    protected function dbIncreaseWrongPasswordAttempts(): void
    {
        $this->increaseCalls++;
    }

    #[Override]
    protected function dbConfirmSuccessfulLogin(): int
    {
        $this->confirmCalls++;

        return 0;
    }

    #[Override]
    protected function dbUpdatePassword(Password $newPassword): void
    {
        $this->storedPassword = $newPassword;
    }
}
