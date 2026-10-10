<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use SensitiveParameter;

/**
 * A stored password: `hash` is the result of `password_hash()` (Argon2id, `PASSWORD_DEFAULT` if the PHP build has no
 * Argon2), `salt` is empty. The hash string carries its salt, algorithm and costs, so nothing else is stored; keep
 * a column of at least 255 characters for the hash.
 *
 * Passwords stored before v4.37.0 have a salt and a SHA-256 hash of salt and password. They are still verified
 * (`isLegacy()`), and `needsRehash()` tells the login to store a new hash after a successful login
 * (`AuthUser::rehashPassword()`). Their verification costs the time of a current one.
 */
final readonly class Password
{
    private const string LEGACY_ALGORITHM = 'sha256';

    public function __construct(public string $salt, public string $hash) {}

    public static function generateNew(#[SensitiveParameter] string $rawPassword): Password
    {
        return new Password(salt: '', hash: password_hash(password: $rawPassword, algo: Password::getAlgorithm()));
    }

    public function isValid(#[SensitiveParameter] string $rawPassword): bool
    {
        if ($this->isLegacy()) {
            $isValid = hash_equals(
                known_string: $this->hash,
                user_string: hash(algo: Password::LEGACY_ALGORITHM, data: $this->salt . $rawPassword),
            );
            // The SHA-256 takes microseconds: the time of a verification is spent anyway, so the answer time does not
            // tell the users with a legacy hash from the others
            Password::spendVerificationTime(rawPassword: $rawPassword);

            return $isValid;
        }

        return password_verify(password: $rawPassword, hash: $this->hash);
    }

    /**
     * Whether the stored hash is outdated (legacy format or weaker costs than today's default) and should be replaced
     * after a successful login.
     */
    public function needsRehash(): bool
    {
        return $this->isLegacy() || password_needs_rehash(hash: $this->hash, algo: Password::getAlgorithm());
    }

    /**
     * Whether this is a password of the format before v4.37.0 (salt and SHA-256).
     */
    public function isLegacy(): bool
    {
        return $this->salt !== '';
    }

    /**
     * Does the work of a password verification and discards the result: hashes with the algorithm and costs of
     * `generateNew()`, which is what verifying a current hash costs (Argon2id, or `PASSWORD_DEFAULT` without Argon2).
     * Called when a login names a user that does not exist, so the response time does not tell which user names exist.
     */
    public static function spendVerificationTime(#[SensitiveParameter] string $rawPassword): void
    {
        password_hash(password: $rawPassword, algo: Password::getAlgorithm());
    }

    private static function getAlgorithm(): string
    {
        return defined(constant_name: 'PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }
}
