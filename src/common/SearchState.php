<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

use actra\yuf\core\HttpRequest;
use actra\yuf\core\InputSourceEnum;
use actra\yuf\session\Session;
use actra\yuf\session\SessionSectionEnum;
use DateMalformedStringException;
use DateTime;

/**
 * Search forms: remembers the values of the search fields of a user in the session. The SQL conditions of the search
 * texts are built by `SearchQueryBuilder`.
 */
final class SearchState
{
    public const string PARAM_RESET = 'reset';
    public const string PARAM_FIND = 'find';

    /**
     * @param string $instanceName The search state of the user is kept in the session below this name; it must be
     *                             unique per page
     * @param Session $session Keeps the search state of the user (`ViewContext::$session`)
     * @param InputSourceEnum $valueSource Where the values of the search fields come from (the query string for a
     *                                     search form with method GET, else the posted data); the parameters `reset`
     *                                     and `find` always come from the query string
     */
    private function __construct(
        private readonly string $instanceName,
        private readonly HttpRequest $httpRequest,
        private readonly InputSourceEnum $valueSource,
        private readonly Session $session,
    ) {}

    public static function create(
        string $instanceName,
        HttpRequest $httpRequest,
        InputSourceEnum $valueSource,
        Session $session,
    ): SearchState {
        return new SearchState(
            instanceName: $instanceName,
            httpRequest: $httpRequest,
            valueSource: $valueSource,
            session: $session,
        );
    }

    private function readString(string $fieldName): ?string
    {
        return match ($this->valueSource) {
            InputSourceEnum::QUERY => $this->httpRequest->getQueryString(name: $fieldName),
            InputSourceEnum::POST => $this->httpRequest->getPostString(name: $fieldName),
        };
    }

    /**
     * @return ?array<array-key, mixed>
     */
    private function readArray(string $fieldName): ?array
    {
        return match ($this->valueSource) {
            InputSourceEnum::QUERY => $this->httpRequest->getQueryArray(name: $fieldName),
            InputSourceEnum::POST => $this->httpRequest->getPostArray(name: $fieldName),
        };
    }

    private function isSearchRequested(): bool
    {
        return $this->httpRequest->hasQueryValue(name: SearchState::PARAM_RESET)
            || $this->httpRequest->hasQueryValue(name: SearchState::PARAM_FIND);
    }

    public function checkSearchTerm(string $default = ''): string
    {
        return $this->checkString(fieldName: 'searchterm', default: $default);
    }

    /**
     * The text of a search field: the input of this request, else the remembered value, else the default. The
     * session is only written when the value changes.
     */
    public function checkString(string $fieldName, string $default = ''): string
    {
        $storedValue = $this->readStoredString(field: $fieldName);
        $value = $this->isSearchRequested() ? $default : $storedValue ?? $default;
        $userInput = $this->readString(fieldName: $fieldName);
        if ($userInput !== null) {
            $value = $userInput;
        }
        $this->storeIfChanged(field: $fieldName, value: $value, storedValue: $storedValue ?? $default);

        return $value;
    }

    /**
     * @param array<array-key, mixed> $array The allowed values as keys
     */
    public function checkFilter(array $array, string $fieldName, string $default = ''): string
    {
        $storedValue = $this->readStoredString(field: $fieldName);
        $value = $this->isSearchRequested() ? $default : $storedValue ?? $default;
        $userInput = $this->readString(fieldName: $fieldName);
        if ($userInput !== null && array_key_exists(key: $userInput, array: $array)) {
            $value = $userInput;
        }
        $this->storeIfChanged(field: $fieldName, value: $value, storedValue: $storedValue ?? $default);

        return $value;
    }

    /**
     * @param array<array-key, mixed> $array The allowed values as keys
     * @param list<int|string> $default
     *
     * @return list<int|string> The chosen keys (a posted value of `<fieldName>ID` is added as string)
     */
    public function checkMultiFilter(array $array, string $fieldName, array $default = []): array
    {
        $storedValue = $this->readStoredList(field: $fieldName);
        $isSearchRequested = $this->isSearchRequested();
        $value = $isSearchRequested ? $default : $storedValue ?? $default;
        if ($isSearchRequested) {
            foreach ($array as $key => $val) {
                $userInput = $this->readArray(fieldName: $fieldName);
                if ($userInput !== null && in_array(needle: (string) $key, haystack: $userInput, strict: true)) {
                    $value[] = $key;
                }
            }
            $requestedValue = $this->readString(fieldName: $fieldName . 'ID');
            if ($requestedValue !== null) {
                $value[] = $requestedValue;
            }
        }
        $this->storeIfChanged(field: $fieldName, value: $value, storedValue: $storedValue ?? $default);

        return $value;
    }

    /**
     * @param array{minDate: string, maxDate: string} $dateRange
     *
     * @return array{dateFrom: DateTime, dateTo: DateTime}
     */
    public function checkDateRangeFilter(
        array $dateRange,
        string $fromField,
        string $toField,
        ?string $defaultFrom = null,
        ?string $defaultTo = null,
    ): array {
        $storedFrom = $this->readStoredString(field: $fromField);
        $storedTo = $this->readStoredString(field: $toField);
        $isSearchRequested = $this->isSearchRequested();
        $inputFrom = $this->readString(fieldName: $fromField);
        $inputTo = $this->readString(fieldName: $toField);
        $dateFromStr = $inputFrom ?? ($isSearchRequested ? null : $storedFrom) ?? $defaultFrom ?? $dateRange['minDate'];
        $dateToStr = $inputTo ?? ($isSearchRequested ? null : $storedTo) ?? $defaultTo ?? $dateRange['maxDate'];

        $dateFromObj = SearchState::checkDate(date: $dateFromStr);
        $dateToObj = SearchState::checkDate(date: $dateToStr);

        $minDateObj = new DateTime(datetime: $dateRange['minDate']);
        $maxDateObj = new DateTime(datetime: $dateRange['maxDate']);

        // if to ist earlier then from, set it to from
        if ($dateFromObj !== null && $dateToObj !== null && $dateFromObj->getTimestamp(
        ) > $dateToObj->getTimestamp()) {
            $dateToObj = $dateFromObj;
        }

        // if from is empty or earlier than minDate, set it to minDate
        if ($dateFromObj === null || $dateFromObj->getTimestamp() < $minDateObj->getTimestamp()) {
            $dateFromObj = $minDateObj;
        }

        // if to is empty or later than maxDate, set it to maxDate
        if ($dateToObj === null || $dateToObj->getTimestamp() > $maxDateObj->getTimestamp()) {
            $dateToObj = $maxDateObj;
        }

        // The defaults are not stored: only what the user chose (or what replaces a remembered value)
        $this->storeDateIfChanged(
            field: $fromField,
            value: $dateFromObj,
            storedValue: $storedFrom,
            hasInput: $inputFrom !== null,
        );
        $this->storeDateIfChanged(
            field: $toField,
            value: $dateToObj,
            storedValue: $storedTo,
            hasInput: $inputTo !== null,
        );

        return ['dateFrom' => $dateFromObj, 'dateTo' => $dateToObj];
    }

    private function storeDateIfChanged(string $field, DateTime $value, ?string $storedValue, bool $hasInput): void
    {
        $formattedValue = $value->format(format: 'd.m.Y');
        if ($storedValue === null ? $hasInput : $storedValue !== $formattedValue) {
            $this->store(field: $field, value: $formattedValue);
        }
    }

    /**
     * @param string|list<int|string> $value
     * @param string|list<int|string> $storedValue What the next request reads without a write (the remembered value,
     *                                             else the default)
     */
    private function storeIfChanged(string $field, string|array $value, string|array $storedValue): void
    {
        if ($value !== $storedValue) {
            $this->store(field: $field, value: $value);
        }
    }

    /**
     * @param string|list<int|string> $value
     */
    private function store(string $field, string|array $value): void
    {
        $data = $this->session->getSection(section: SessionSectionEnum::SEARCH);
        $instanceData = $this->readInstanceData(sectionData: $data);
        $instanceData[$field] = $value;
        $data[$this->instanceName] = $instanceData;
        $this->session->setSection(section: SessionSectionEnum::SEARCH, data: $data);
    }

    private function readStoredString(string $field): ?string
    {
        $value = $this->readStored(field: $field);

        return is_string(value: $value) ? $value : null;
    }

    /**
     * @return ?list<int|string>
     */
    private function readStoredList(string $field): ?array
    {
        $value = $this->readStored(field: $field);
        if (!is_array(value: $value)) {
            return null;
        }
        $list = [];
        foreach ($value as $item) {
            if (is_int(value: $item) || is_string(value: $item)) {
                $list[] = $item;
            }
        }

        return $list;
    }

    /**
     * @return string|int|float|bool|array<array-key, mixed>|null
     */
    private function readStored(string $field): string|int|float|bool|array|null
    {
        $instanceData = $this->readInstanceData(
            sectionData: $this->session->getSection(section: SessionSectionEnum::SEARCH),
        );
        if (!array_key_exists(key: $field, array: $instanceData)) {
            return null;
        }
        $value = $instanceData[$field];

        return is_scalar(value: $value) || is_array(value: $value) ? $value : null;
    }

    /**
     * @param array<array-key, mixed> $sectionData
     *
     * @return array<array-key, mixed> The remembered values of this search form, empty if there are none
     */
    private function readInstanceData(array $sectionData): array
    {
        if (!array_key_exists(key: $this->instanceName, array: $sectionData)) {
            return [];
        }
        $instanceData = $sectionData[$this->instanceName];

        return is_array(value: $instanceData) ? $instanceData : [];
    }

    public static function checkDate(string $date): ?DateTime
    {
        if ($date === '') {
            return null;
        }
        try {
            $dateTime = new DateTime(datetime: $date);
            if (DateTime::getLastErrors() !== false) {
                return null;
            }

            return $dateTime;
        } catch (DateMalformedStringException) {
            return null;
        }
    }
}
