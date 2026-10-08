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

    public function getId(): string;

    /**
     * Gives the session a new ID and deletes the old session.
     */
    public function regenerateId(): void;
}
