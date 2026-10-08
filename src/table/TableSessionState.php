<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table;

use actra\yuf\session\Session;
use actra\yuf\session\SessionSectionEnum;

/**
 * The state of tables and table filters in the session: strings per identifier and index, below one section of yuf
 * (and optionally one group, so different kinds of state cannot collide). Reading never writes; writing a value that
 * is stored already does nothing.
 *
 * @internal
 */
final readonly class TableSessionState
{
    /** The group of the values of a table filter (own state of the filter). */
    public const string GROUP_FILTERS = 'filters';
    /** The group of the values of the fields of table filters. */
    public const string GROUP_FIELDS = 'fields';

    public function __construct(
        private Session $session,
        private SessionSectionEnum $section,
        private ?string $group = null,
    ) {}

    public function get(string $identifier, string $index): ?string
    {
        $entry = $this->readEntry(identifier: $identifier);
        if (!array_key_exists(key: $index, array: $entry)) {
            return null;
        }
        $value = $entry[$index];

        return is_string(value: $value) ? $value : null;
    }

    public function set(string $identifier, string $index, string $value): void
    {
        if ($this->get(identifier: $identifier, index: $index) === $value) {
            return;
        }
        $entries = $this->readEntries();
        $entries[$identifier][$index] = $value;
        $this->writeEntries(entries: $entries);
    }

    public function remove(string $identifier, string $index): void
    {
        if ($this->get(identifier: $identifier, index: $index) === null) {
            return;
        }
        $entries = $this->readEntries();
        $entry = $this->readEntry(identifier: $identifier);
        unset($entry[$index]);
        if ($entry === []) {
            unset($entries[$identifier]);
        } else {
            $entries[$identifier] = $entry;
        }
        $this->writeEntries(entries: $entries);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function readEntry(string $identifier): array
    {
        $entries = $this->readEntries();

        return array_key_exists(key: $identifier, array: $entries) ? $entries[$identifier] : [];
    }

    /**
     * @return array<array-key, array<array-key, mixed>>
     */
    private function readEntries(): array
    {
        $data = $this->session->getSection(section: $this->section);
        if ($this->group !== null) {
            $data = array_key_exists(key: $this->group, array: $data) ? $data[$this->group] : [];
        }
        $entries = [];
        if (is_array(value: $data)) {
            foreach ($data as $identifier => $values) {
                if (is_array(value: $values)) {
                    $entries[$identifier] = $values;
                }
            }
        }

        return $entries;
    }

    /**
     * @param array<array-key, array<array-key, mixed>> $entries
     */
    private function writeEntries(array $entries): void
    {
        if ($this->group === null) {
            $this->session->setSection(section: $this->section, data: $entries);

            return;
        }
        $data = $this->session->getSection(section: $this->section);
        if ($entries === []) {
            unset($data[$this->group]);
        } else {
            $data[$this->group] = $entries;
        }
        $this->session->setSection(section: $this->section, data: $data);
    }
}
