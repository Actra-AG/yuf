<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\session;

use Closure;
use InvalidArgumentException;

/**
 * The session of the current request and the only way to read and write session data outside of the session handler
 * (also for the own data of a project, e.g. a cart or flash messages). `Core` creates one per request
 * (`Core::$session`, `null` without sessions); views get it as `ViewContext::$session`. The PHP session starts on the
 * first access (read or write); `Core` closes it after the view, `close()` does it earlier.
 *
 * Values are strings, numbers, booleans, `null` and arrays of these, nested as deep as needed (no objects, so
 * nothing is (un)serialized with surprises; `set()` checks arrays recursively, the PHPDoc alias cannot express it). The
 * typed getters return `null` for a missing key or a value of another type or shape (`getStringList()`,
 * `getStringMap()`, `getStruct()` for arrays) and never write.
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
     * Only a list (keys 0, 1, 2, … in order) whose values are all strings; `[]` is a valid list.
     *
     * @return ?list<string>
     */
    public function getStringList(string $key): ?array
    {
        $value = $this->getArray(key: $key);
        if ($value === null || !array_is_list(array: $value)) {
            return null;
        }
        $strings = [];
        foreach ($value as $item) {
            if (!is_string(value: $item)) {
                return null;
            }
            $strings[] = $item;
        }

        return $strings;
    }

    /**
     * Only an array whose keys and values are all strings (numeric string keys like `'1'` are integers in PHP and
     * make the value invalid); `[]` is a valid map.
     *
     * @return ?array<string, string>
     */
    public function getStringMap(string $key): ?array
    {
        $value = $this->getArray(key: $key);
        if ($value === null) {
            return null;
        }
        $map = [];
        foreach ($value as $itemKey => $item) {
            if (!is_string(value: $itemKey) || !is_string(value: $item)) {
                return null;
            }
            $map[$itemKey] = $item;
        }

        return $map;
    }

    /**
     * Maps a stored array to a value object, e.g. `fn(array $data): ?Cart => Cart::fromSessionArray(data: $data)`. The
     * mapper narrows the untyped array and returns `null` for an invalid shape.
     *
     * @template T
     * @param Closure(array<array-key, mixed>): ?T $map
     *
     * @return ?T `null` if the key is missing or the value is no array, otherwise the result of the mapper
     */
    public function getStruct(string $key, Closure $map): mixed
    {
        $value = $this->getArray(key: $key);

        return $value === null ? null : $map($value);
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
     * Whether the visitor has a session: it was started in this request or the request carries a valid session
     * cookie. Does not start the session, so it is the way to ask without creating a session (and a cookie) for a
     * visitor who has none.
     */
    public function isActive(): bool
    {
        return $this->storage->isActive();
    }

    /**
     * Writes the session and releases its lock, so parallel requests of the user (e.g. loading a page while a long
     * export runs) do not wait any longer. `Core` does this after the view anyway; call it earlier in a view that runs
     * long, after the last write. Afterwards reading still works, but `set()`, `remove()`, `regenerateId()`,
     * `clearUserData()` and everything else that writes (login, CSRF token, table and search state) throws a
     * `LogicException`; a session that was not used before cannot be used any more.
     */
    public function close(): void
    {
        $this->storage->close();
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
            data: $handlerData === []
                ? []
                : [SessionSectionEnum::ROOT_KEY => [SessionSectionEnum::HANDLER->value => $handlerData]],
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
