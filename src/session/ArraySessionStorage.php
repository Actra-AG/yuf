<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\session;

use Override;

/**
 * Keeps the session data in memory: for tests and scripts without a PHP session. The ID changes with
 * `regenerateId()` (`<id>`, `<id>-1`, `<id>-2`, …).
 *
 * @phpstan-import-type SessionValue from Session
 */
final class ArraySessionStorage implements SessionStorage
{
    private int $regenerations = 0;

    /**
     * @param array<string, SessionValue> $data
     */
    public function __construct(private array $data = [], private readonly string $id = 'array-session') {}

    #[Override]
    public function has(string $key): bool
    {
        return array_key_exists(key: $key, array: $this->data);
    }

    #[Override]
    public function get(string $key): string|int|float|bool|array|null
    {
        return array_key_exists(key: $key, array: $this->data) ? $this->data[$key] : null;
    }

    #[Override]
    public function set(string $key, string|int|float|bool|array|null $value): void
    {
        $this->data[$key] = $value;
    }

    #[Override]
    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    #[Override]
    public function all(): array
    {
        return $this->data;
    }

    #[Override]
    public function replaceAll(array $data): void
    {
        $this->data = $data;
    }

    #[Override]
    public function getId(): string
    {
        return $this->regenerations === 0 ? $this->id : $this->id . '-' . $this->regenerations;
    }

    #[Override]
    public function regenerateId(): void
    {
        $this->regenerations++;
    }
}
