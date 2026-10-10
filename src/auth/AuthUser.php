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
 * in the `db…()` methods). `Authenticator` reads the user, counts the password attempts, upgrades outdated password
 * hashes and confirms a successful login through it.
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

    /**
     * Counts a password attempt in the database before the password is checked. `false` if the user is locked out
     * already (the limit is reached): the password must not be checked then. Several requests of one user cannot get
     * more attempts than the limit, because the database decides (see `dbRegisterWrongPasswordAttempt()`). The
     * attempt counts until `releaseWrongPasswordAttempt()` gives it back after the right password.
     *
     * The lock stays until the project sets a new password and resets the counter itself (e.g. with the new
     * password): a successful login does not reset it.
     */
    public function registerWrongPasswordAttempt(int $maxAllowedWrongPasswordAttempts): bool
    {
        $isRegistered = $this->dbRegisterWrongPasswordAttempt(
            maxAllowedWrongPasswordAttempts: $maxAllowedWrongPasswordAttempts,
        );
        if (!$isRegistered) {
            $this->wrongPasswordAttempts = max($this->wrongPasswordAttempts, $maxAllowedWrongPasswordAttempts);

            return false;
        }
        $this->wrongPasswordAttempts++;

        return true;
    }

    /**
     * Gives back the attempt of `registerWrongPasswordAttempt()` after the right password (or the right second factor):
     * the counter is as before the login.
     */
    public function releaseWrongPasswordAttempt(): void
    {
        $this->dbReleaseWrongPasswordAttempt();
        $this->wrongPasswordAttempts = max(0, $this->wrongPasswordAttempts - 1);
    }

    /**
     * Atomically counts one attempt, only if the user is below the limit, in one statement:
     * `UPDATE users SET wrong_login_attempts = wrong_login_attempts + 1 WHERE id = ? AND wrong_login_attempts < ?`
     * (the limit is the bound argument). Never read the counter first and write it afterwards: parallel requests would
     * all pass the check.
     *
     * @return bool `true` if the attempt was counted (one row changed), `false` if the user reached the limit
     */
    abstract protected function dbRegisterWrongPasswordAttempt(int $maxAllowedWrongPasswordAttempts): bool;

    /**
     * Gives back one attempt, in one statement:
     * `UPDATE users SET wrong_login_attempts = wrong_login_attempts - 1 WHERE id = ? AND wrong_login_attempts > 0`.
     */
    abstract protected function dbReleaseWrongPasswordAttempt(): void;

    /**
     * Does not reset the wrong password attempts: a user who guessed rightly after `N - 1` wrong attempts is not
     * back at zero. Only a new password resets them (in the project's own code that sets it).
     */
    public function confirmSuccessfulLogin(): int
    {
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
