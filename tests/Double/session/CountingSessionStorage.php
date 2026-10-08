<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\session;

use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\SessionStorage;
use Override;

/**
 * An in-memory storage that counts the accesses to the data (`has()`, `get()`, `set()`, `remove()`, `all()`,
 * `replaceAll()`, `getId()`, `regenerateId()`), so a test can prove that the session was not used. `isActive()` and
 * `close()` do not count: asking is no use.
 */
final class CountingSessionStorage implements SessionStorage
{
    public int $accesses = 0;
    private readonly ArraySessionStorage $storage;

    public function __construct(bool $active)
    {
        $this->storage = new ArraySessionStorage(active: $active);
    }

    #[Override]
    public function has(string $key): bool
    {
        $this->accesses++;

        return $this->storage->has(key: $key);
    }

    #[Override]
    public function get(string $key): string|int|float|bool|array|null
    {
        $this->accesses++;

        return $this->storage->get(key: $key);
    }

    #[Override]
    public function set(string $key, string|int|float|bool|array|null $value): void
    {
        $this->accesses++;
        $this->storage->set(key: $key, value: $value);
    }

    #[Override]
    public function remove(string $key): void
    {
        $this->accesses++;
        $this->storage->remove(key: $key);
    }

    #[Override]
    public function all(): array
    {
        $this->accesses++;

        return $this->storage->all();
    }

    #[Override]
    public function replaceAll(array $data): void
    {
        $this->accesses++;
        $this->storage->replaceAll(data: $data);
    }

    #[Override]
    public function isActive(): bool
    {
        return $this->storage->isActive();
    }

    #[Override]
    public function getId(): string
    {
        $this->accesses++;

        return $this->storage->getId();
    }

    #[Override]
    public function regenerateId(): void
    {
        $this->accesses++;
        $this->storage->regenerateId();
    }

    #[Override]
    public function close(): void
    {
        $this->storage->close();
    }
}
