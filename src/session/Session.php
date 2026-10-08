<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\session;

use InvalidArgumentException;

/**
 * The session of the current request and the only way to read and write session data outside of the session handler
 * (also for the own data of a project, e.g. a cart or flash messages). `Core` creates one per request
 * (`Core::$session`, `null` without sessions); views get it as `ViewContext::$session`.
 *
 * Values are strings, numbers, booleans, `null` and arrays of these, nested as deep as needed (no objects, so
 * nothing is (un)serialized with surprises; `set()` checks arrays recursively, the PHPDoc alias cannot express it). The typed getters return `null` for a missing key or a value of another type and never write.
 * The key `yuf` belongs to yuf (see `SessionSectionEnum`).
 *
 * @phpstan-type SessionValue string|int|float|bool|array<array-key, mixed>|null
 */
final readonly class Session
{
    public function __construct(private SessionStorage $storage) {}

    public function has(string $key): bool
    {
        return $this->storage->has(key: $key);
    }

    public function getString(string $key): ?string
    {
        $value = $this->storage->get(key: $key);

        return is_string(value: $value) ? $value : null;
    }

    public function getInt(string $key): ?int
    {
        $value = $this->storage->get(key: $key);

        return is_int(value: $value) ? $value : null;
    }

    public function getFloat(string $key): ?float
    {
        $value = $this->storage->get(key: $key);

        return is_float(value: $value) ? $value : null;
    }

    public function getBool(string $key): ?bool
    {
        $value = $this->storage->get(key: $key);

        return is_bool(value: $value) ? $value : null;
    }

    /**
     * @return ?array<array-key, mixed>
     */
    public function getArray(string $key): ?array
    {
        $value = $this->storage->get(key: $key);

        return is_array(value: $value) ? $value : null;
    }

    /**
     * @param SessionValue $value
     *
     * @throws InvalidArgumentException for the key reserved for yuf
     */
    public function set(string $key, string|int|float|bool|array|null $value): void
    {
        $this->assertProjectKey(key: $key);
        Session::assertValue(value: $value);
        $this->storage->set(key: $key, value: $value);
    }

    /**
     * @throws InvalidArgumentException for the key reserved for yuf
     */
    public function remove(string $key): void
    {
        $this->assertProjectKey(key: $key);
        $this->storage->remove(key: $key);
    }

    public function getId(): string
    {
        return $this->storage->getId();
    }

    /**
     * Gives the session a new ID and deletes the old session (on login, logout and privilege changes).
     */
    public function regenerateId(): void
    {
        $this->storage->regenerateId();
    }

    /**
     * Removes all data of the user from the session, e.g. on logout (login state, CSRF token, table and search
     * state, uploads, project data, …). Keeps only the data of the session handler (`SessionSectionEnum::HANDLER`),
     * which includes the preferred language.
     */
    public function clearUserData(): void
    {
        $handlerData = $this->getSection(section: SessionSectionEnum::HANDLER);
        $this->storage->replaceAll(
            data: $handlerData === [] ? [] : [SessionSectionEnum::ROOT_KEY => [SessionSectionEnum::HANDLER->value => $handlerData]],
        );
    }

    /**
     * All session data, for the debug page.
     *
     * @return array<string, SessionValue>
     */
    public function export(): array
    {
        return $this->storage->all();
    }

    /**
     * The data of one section of yuf; an empty array if there is none (nothing is written).
     *
     * @return array<array-key, mixed>
     *
     * @internal For the classes of yuf that keep their state in the session
     */
    public function getSection(SessionSectionEnum $section): array
    {
        $yufData = $this->storage->get(key: SessionSectionEnum::ROOT_KEY);
        if (!is_array(value: $yufData) || !array_key_exists(key: $section->value, array: $yufData)) {
            return [];
        }
        $sectionData = $yufData[$section->value];

        return is_array(value: $sectionData) ? $sectionData : [];
    }

    /**
     * @param array<array-key, mixed> $data Replaces the whole section; an empty array removes it
     *
     * @internal For the classes of yuf that keep their state in the session
     */
    public function setSection(SessionSectionEnum $section, array $data): void
    {
        $yufData = $this->storage->get(key: SessionSectionEnum::ROOT_KEY);
        $yufData = is_array(value: $yufData) ? $yufData : [];
        if ($data === []) {
            unset($yufData[$section->value]);
        } else {
            $yufData[$section->value] = $data;
        }
        if ($yufData === []) {
            $this->storage->remove(key: SessionSectionEnum::ROOT_KEY);

            return;
        }
        $this->storage->set(key: SessionSectionEnum::ROOT_KEY, value: $yufData);
    }

    /**
     * @throws InvalidArgumentException for an object, a resource or any other value that is not stored as it is
     */
    private static function assertValue(mixed $value): void
    {
        if ($value === null || is_scalar(value: $value)) {
            return;
        }
        if (!is_array(value: $value)) {
            throw new InvalidArgumentException(
                message: 'A session value must be a string, number, boolean, null or an array of these, '
                . get_debug_type(value: $value) . ' given.',
            );
        }
        foreach ($value as $item) {
            Session::assertValue(value: $item);
        }
    }

    private function assertProjectKey(string $key): void
    {
        if ($key === SessionSectionEnum::ROOT_KEY) {
            throw new InvalidArgumentException(
                message: 'The session key "' . SessionSectionEnum::ROOT_KEY . '" is reserved for yuf.',
            );
        }
    }
}
