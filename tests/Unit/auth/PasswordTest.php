<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\Password;
use PHPUnit\Framework\TestCase;

/**
 * New passwords are `password_hash()` hashes without salt of their own; passwords stored before v4.37.0 (random salt
 * and SHA-256 of salt and password) are still verified (the tests of that format passed against the old class).
 */
final class PasswordTest extends TestCase
{
    private const string LEGACY_SALT = 'abcdefghijklmnop';

    public function testGeneratedPasswordIsAnArgon2idHashWithoutSalt(): void
    {
        $password = Password::generateNew(rawPassword: 'secret');

        $this->assertSame('', $password->salt);
        $this->assertStringStartsWith('$argon2id$', $password->hash);
        $this->assertTrue(password_verify(password: 'secret', hash: $password->hash));
        $this->assertFalse($password->isLegacy());
    }

    public function testTwoGeneratedPasswordsOfTheSameInputDiffer(): void
    {
        $first = Password::generateNew(rawPassword: 'secret');
        $second = Password::generateNew(rawPassword: 'secret');

        $this->assertNotSame($first->hash, $second->hash);
    }

    public function testGeneratedPasswordIsValidForTheRawPassword(): void
    {
        $this->assertTrue(Password::generateNew(rawPassword: 'secret')->isValid(rawPassword: 'secret'));
    }

    public function testGeneratedPasswordIsInvalidForAnotherPassword(): void
    {
        $this->assertFalse(Password::generateNew(rawPassword: 'secret')->isValid(rawPassword: 'Secret'));
    }

    public function testGeneratedPasswordIsInvalidForAnEmptyPassword(): void
    {
        $this->assertFalse(Password::generateNew(rawPassword: 'secret')->isValid(rawPassword: ''));
    }

    public function testEmptyPasswordCanBeStoredAndChecked(): void
    {
        $password = Password::generateNew(rawPassword: '');

        $this->assertTrue($password->isValid(rawPassword: ''));
        $this->assertFalse($password->isValid(rawPassword: ' '));
    }

    public function testUnicodePasswordIsValid(): void
    {
        $this->assertTrue(Password::generateNew(rawPassword: 'Pässwörd€')->isValid(rawPassword: 'Pässwörd€'));
    }

    public function testVeryLongPasswordIsComparedCompletely(): void
    {
        $password = Password::generateNew(rawPassword: str_repeat(string: 'a', times: 200) . 'x');

        $this->assertFalse($password->isValid(rawPassword: str_repeat(string: 'a', times: 200) . 'y'));
    }

    public function testPasswordWithNullByteIsComparedCompletely(): void
    {
        $password = Password::generateNew(rawPassword: "abc\0def");

        $this->assertTrue($password->isValid(rawPassword: "abc\0def"));
        $this->assertFalse($password->isValid(rawPassword: 'abc'));
    }

    public function testNewPasswordNeedsNoRehash(): void
    {
        $this->assertFalse(Password::generateNew(rawPassword: 'secret')->needsRehash());
    }

    public function testHashWithWeakerCostsNeedsRehash(): void
    {
        $weakHash = password_hash(
            password: 'secret',
            algo: PASSWORD_ARGON2ID,
            options: ['memory_cost' => 1024, 'time_cost' => 1],
        );
        $password = new Password(salt: '', hash: $weakHash);

        $this->assertTrue($password->isValid(rawPassword: 'secret'));
        $this->assertTrue($password->needsRehash());
    }

    public function testBcryptHashIsValidAndNeedsRehash(): void
    {
        $password = new Password(salt: '', hash: password_hash(password: 'secret', algo: PASSWORD_BCRYPT));

        $this->assertTrue($password->isValid(rawPassword: 'secret'));
        $this->assertTrue($password->needsRehash());
    }

    public function testLegacyPasswordIsValidForTheRawPassword(): void
    {
        $password = $this->createLegacyPassword(rawPassword: 'secret');

        $this->assertTrue($password->isLegacy());
        $this->assertTrue($password->isValid(rawPassword: 'secret'));
        $this->assertFalse($password->isValid(rawPassword: 'other'));
        $this->assertFalse($password->isValid(rawPassword: ''));
    }

    public function testLegacyPasswordNeedsRehash(): void
    {
        $this->assertTrue($this->createLegacyPassword(rawPassword: 'secret')->needsRehash());
    }

    public function testLegacyPasswordWithUnicodeIsValid(): void
    {
        $password = $this->createLegacyPassword(rawPassword: 'Pässwörd€');

        $this->assertTrue($password->isValid(rawPassword: 'Pässwörd€'));
    }

    public function testLegacyHashIsComparedCaseSensitive(): void
    {
        $password = new Password(
            salt: PasswordTest::LEGACY_SALT,
            hash: strtoupper(string: hash(algo: 'sha256', data: PasswordTest::LEGACY_SALT . 'secret')),
        );

        $this->assertFalse($password->isValid(rawPassword: 'secret'));
    }

    public function testEmptyHashIsNeverValid(): void
    {
        $this->assertFalse(new Password(salt: '', hash: '')->isValid(rawPassword: ''));
        $this->assertFalse(new Password(salt: PasswordTest::LEGACY_SALT, hash: '')->isValid(rawPassword: ''));
    }

    public function testSpendVerificationTimeDoesNotFail(): void
    {
        Password::spendVerificationTime(rawPassword: 'anything');

        $this->expectNotToPerformAssertions();
    }

    private function createLegacyPassword(string $rawPassword): Password
    {
        return new Password(
            salt: PasswordTest::LEGACY_SALT,
            hash: hash(algo: 'sha256', data: PasswordTest::LEGACY_SALT . $rawPassword),
        );
    }
}
