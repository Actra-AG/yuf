<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\session;

use LogicException;
use Override;

/**
 * The session data in PHP's `$_SESSION`. Together with the session handler, this is the only class that touches
 * `$_SESSION`. The session must be started (by the handler) before the storage is used. Values that are not
 * supported by `Session` (objects, resources) are read as `null` and left out of arrays.
 *
 * @phpstan-import-type SessionValue from Session
 */
final readonly class NativeSessionStorage implements SessionStorage
{
    public function __construct(private AbstractSessionHandler $sessionHandler) {}

    #[Override]
    public function has(string $key): bool
    {
        return array_key_exists(key: $key, array: $this->readAll());
    }

    #[Override]
    public function get(string $key): string|int|float|bool|array|null
    {
        return NativeSessionStorage::narrow(value: $this->readAll()[$key] ?? null);
    }

    #[Override]
    public function set(string $key, string|int|float|bool|array|null $value): void
    {
        $this->assertStarted();
        $_SESSION[$key] = $value;
    }

    #[Override]
    public function remove(string $key): void
    {
        $this->assertStarted();
        unset($_SESSION[$key]);
    }

    #[Override]
    public function all(): array
    {
        $all = [];
        foreach ($this->readAll() as $key => $value) {
            $all[(string) $key] = NativeSessionStorage::narrow(value: $value);
        }

        return $all;
    }

    #[Override]
    public function replaceAll(array $data): void
    {
        $this->assertStarted();
        $_SESSION = $data;
    }

    #[Override]
    public function getId(): string
    {
        return $this->sessionHandler->getId();
    }

    #[Override]
    public function regenerateId(): void
    {
        $this->sessionHandler->regenerateId();
    }

    /**
     * @return array<array-key, mixed>
     */
    private function readAll(): array
    {
        $this->assertStarted();

        return $_SESSION;
    }

    private function assertStarted(): void
    {
        if (!is_array(value: $_SESSION ?? null)) {
            throw new LogicException(message: 'The session is not started.');
        }
    }

    /**
     * @return SessionValue
     */
    private static function narrow(mixed $value): string|int|float|bool|array|null
    {
        if ($value === null || is_string(value: $value) || is_int(value: $value) || is_float(value: $value)
            || is_bool(value: $value)) {
            return $value;
        }
        if (!is_array(value: $value)) {
            return null;
        }
        $narrowed = [];
        foreach ($value as $key => $item) {
            if ($item === null || is_scalar(value: $item) || is_array(value: $item)) {
                $narrowed[$key] = NativeSessionStorage::narrow(value: $item);
            }
        }

        return $narrowed;
    }
}
