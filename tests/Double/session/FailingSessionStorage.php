<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\session;

use actra\yuf\session\SessionStorage;
use Override;
use RuntimeException;

/**
 * A storage whose session cannot be started: every access throws, like the first access to a session whose save path
 * is not writable.
 */
final class FailingSessionStorage implements SessionStorage
{
    #[Override]
    public function has(string $key): bool
    {
        throw FailingSessionStorage::failure();
    }

    #[Override]
    public function get(string $key): string|int|float|bool|array|null
    {
        throw FailingSessionStorage::failure();
    }

    #[Override]
    public function set(string $key, string|int|float|bool|array|null $value): void
    {
        throw FailingSessionStorage::failure();
    }

    #[Override]
    public function remove(string $key): void
    {
        throw FailingSessionStorage::failure();
    }

    #[Override]
    public function all(): array
    {
        throw FailingSessionStorage::failure();
    }

    #[Override]
    public function replaceAll(array $data): void
    {
        throw FailingSessionStorage::failure();
    }

    #[Override]
    public function isActive(): bool
    {
        return true;
    }

    #[Override]
    public function getId(): string
    {
        throw FailingSessionStorage::failure();
    }

    #[Override]
    public function regenerateId(): void
    {
        throw FailingSessionStorage::failure();
    }

    #[Override]
    public function close(): void {}

    private static function failure(): RuntimeException
    {
        return new RuntimeException(message: 'The session could not be started.');
    }
}
