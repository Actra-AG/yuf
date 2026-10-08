<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use InvalidArgumentException;
use SensitiveParameter;

/**
 * The stored hash of a random machine token (API key secret, reset link, remember-me token): SHA-256 of the secret as
 * 64 lowercase hex characters. Use `Password` (Argon2id) for passwords that humans choose, and this class for secrets
 * that the application generated (`generate()`: 32 random bytes by default, base64url without padding).
 *
 * A fast hash is safe here because the secret has at least 128 bits of entropy: nobody can guess it, so there is no
 * dictionary to try and a slow hash would add nothing, but it would cost 50 ms and 64 MB on every request that carries
 * the token. A password has little entropy: an attacker with the stored hash tries billions of guesses per second
 * against SHA-256, which is why passwords need a slow hash. Do not use this class for anything a human types or
 * chooses, or for secrets shorter than 16 random bytes.
 */
final readonly class SecretTokenHash
{
    private const string HASH_ALGORITHM = 'sha256';
    private const string HASH_PATTERN = '/^[0-9a-f]{64}$/D';
    private const int MIN_BYTES = 16;

    /**
     * @param string $hash The stored value (64 lowercase hex characters)
     *
     * @throws InvalidArgumentException if the hash has another format
     */
    public function __construct(public string $hash)
    {
        if (preg_match(pattern: SecretTokenHash::HASH_PATTERN, subject: $hash) !== 1) {
            throw new InvalidArgumentException(
                message: 'A secret token hash must consist of 64 lowercase hexadecimal characters.',
            );
        }
    }

    /**
     * Creates a new random secret. Show `$secret` once and store the hash.
     *
     * @throws InvalidArgumentException if fewer than 16 bytes are requested
     */
    public static function generate(int $bytes = 32): GeneratedSecretToken
    {
        if ($bytes < SecretTokenHash::MIN_BYTES) {
            throw new InvalidArgumentException(
                message: 'A secret token needs at least ' . SecretTokenHash::MIN_BYTES . ' random bytes.',
            );
        }
        $secret = rtrim(
            string: strtr(string: base64_encode(string: random_bytes(length: $bytes)), from: '+/', to: '-_'),
            characters: '=',
        );

        return new GeneratedSecretToken(secret: $secret, hash: SecretTokenHash::fromSecret(secret: $secret));
    }

    /**
     * @throws InvalidArgumentException if the secret is empty
     */
    public static function fromSecret(#[SensitiveParameter] string $secret): SecretTokenHash
    {
        if ($secret === '') {
            throw new InvalidArgumentException(message: 'A secret token must not be empty.');
        }

        return new SecretTokenHash(hash: hash(algo: SecretTokenHash::HASH_ALGORITHM, data: $secret));
    }

    public function isValid(#[SensitiveParameter] string $secret): bool
    {
        return hash_equals(
            known_string: $this->hash,
            user_string: hash(algo: SecretTokenHash::HASH_ALGORITHM, data: $secret),
        );
    }
}
