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
    /**
     * @param list<string> $accessRights
     */
    public static function create(array $accessRights, bool $isActive = true): TestAuthUser
    {
        return new TestAuthUser(
            id: 1,
            isActive: $isActive,
            wrongPasswordAttempts: 0,
            accessRightCollection: AccessRightCollection::createFromStringArray(input: $accessRights),
            password: Password::generateNew(rawPassword: 'test'),
            ipWhitelist: [],
        );
    }

    #[Override]
    protected function dbIncreaseWrongPasswordAttempts(): void {}

    #[Override]
    protected function dbConfirmSuccessfulLogin(): int
    {
        return 0;
    }
}
