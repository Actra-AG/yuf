<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use SensitiveParameter;

/**
 * Extension point: the user of a login, as the project loads it (a project class extends it and persists the changes
 * in the `db…()` methods). `Authenticator` reads the user, counts wrong passwords, upgrades outdated password hashes
 * and confirms a successful login through it.
 */
abstract class AuthUser
{
    /**
     * @param ?Password $password `null` for a user without password: a password login is refused
     *     (`AuthResultEnum::ERROR_NO_PASSWORD_LOGIN_ACTIVE`, not counted), the other login methods work
     * @param list<string> $ipWhitelist IP addresses and ranges the user may log in from (see
     *     `IpValidator::isInWhitelist()`), empty for all
     */
    public function __construct(
        public readonly int $id,
        public readonly bool $isActive,
        public private(set) int $wrongPasswordAttempts,
        private readonly AccessRightCollection $accessRightCollection,
        public private(set) ?Password $password,
        public readonly array $ipWhitelist,
    ) {}

    public function hasOneOfRights(AccessRightCollection $accessRightCollection): bool
    {
        if (!$this->isActive) {
            return false;
        }

        return $this->accessRightCollection->hasOneOfAccessRights(accessRightCollection: $accessRightCollection);
    }

    public function increaseWrongPasswordAttempts(): void
    {
        $this->dbIncreaseWrongPasswordAttempts();
        $this->wrongPasswordAttempts++;
    }

    abstract protected function dbIncreaseWrongPasswordAttempts(): void;

    public function confirmSuccessfulLogin(): int
    {
        $this->wrongPasswordAttempts = 0;

        return $this->dbConfirmSuccessfulLogin();
    }

    /**
     * @return int The ID of the new login (`AuthSession::getAuthSessionId()`)
     */
    abstract protected function dbConfirmSuccessfulLogin(): int;

    /**
     * Stores a new hash of the password the user just logged in with: called by the `Authenticator` after a
     * successful password login if `Password::needsRehash()` says the stored hash is outdated.
     */
    public function rehashPassword(#[SensitiveParameter] string $rawPassword): void
    {
        $newPassword = Password::generateNew(rawPassword: $rawPassword);
        $this->dbUpdatePassword(newPassword: $newPassword);
        $this->password = $newPassword;
    }

    /**
     * Persists the new password hash (`Password::$salt` and `Password::$hash`). Does not change the wrong password
     * attempts.
     */
    abstract protected function dbUpdatePassword(Password $newPassword): void;
}
