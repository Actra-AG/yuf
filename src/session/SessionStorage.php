<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\session;

/**
 * Where the data of a `Session` lives. `NativeSessionStorage` is the production implementation (PHP's `$_SESSION`),
 * `ArraySessionStorage` keeps the data in memory (tests, CLI).
 *
 * @phpstan-import-type SessionValue from Session
 */
interface SessionStorage
{
    public function has(string $key): bool;

    /**
     * @return SessionValue `null` for a missing key
     */
    public function get(string $key): string|int|float|bool|array|null;

    /**
     * @param SessionValue $value
     */
    public function set(string $key, string|int|float|bool|array|null $value): void;

    public function remove(string $key): void;

    /**
     * @return array<string, SessionValue>
     */
    public function all(): array;

    /**
     * @param array<string, SessionValue> $data
     */
    public function replaceAll(array $data): void;

    /**
     * Whether the visitor has a session: it was started in this request or the request carries a valid session
     * cookie. Must not start the session.
     */
    public function isActive(): bool;

    public function getId(): string;

    /**
     * Gives the session a new ID and deletes the old session.
     */
    public function regenerateId(): void;

    /**
     * Writes the data and releases what the storage holds (e.g. the lock of the session file), so parallel requests
     * of the user do not wait any longer. Afterwards the data can still be read, but `set()`, `remove()`,
     * `replaceAll()` and `regenerateId()` throw a `LogicException`. Closing twice does nothing.
     */
    public function close(): void;
}
