<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

use actra\yuf\core\HttpRequest;
use actra\yuf\core\InputSourceEnum;
use actra\yuf\db\DbQueryData;
use actra\yuf\session\Session;
use actra\yuf\session\SessionSectionEnum;
use DateMalformedStringException;
use DateTime;
use InvalidArgumentException;
use NoDiscard;

/**
 * Search forms: remembers the values of the search fields of a user in the session and builds the parameterized SQL
 * conditions of the search texts. The SQL builders are static on purpose: they are pure and have no state.
 */
final class SearchHelper
{
    public const string PARAM_RESET = 'reset';
    public const string PARAM_FIND = 'find';
    /**
     * Not "\": with the MySQL mode NO_BACKSLASH_ESCAPES, a backslash is neither the default escape character of LIKE
     * nor can it be written as the same string literal in both modes.
     */
    private const string LIKE_PLACEHOLDER = ' LIKE ? ESCAPE \'!\'';
    private const array LIKE_ESCAPE_MAP = ['!' => '!!', '%' => '!%', '_' => '!_'];
    /** An unquoted column name must not consist of digits only; a "?" would be counted as a placeholder. */
    private const string COLUMN_NAME_PART = '(`[^`?\s.]+`|[0-9a-z_$]*[a-z_$][0-9a-z_$]*)';
    private const string FIELD_NAME_PATTERN = '/^' . SearchHelper::COLUMN_NAME_PART . '(\.'
        . SearchHelper::COLUMN_NAME_PART . '){0,2}$/iD';

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
    ): SearchHelper {
        return new SearchHelper(
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
        return $this->httpRequest->hasQueryValue(name: SearchHelper::PARAM_RESET)
            || $this->httpRequest->hasQueryValue(name: SearchHelper::PARAM_FIND);
    }

    /**
     * Builds the "WHERE" condition of table filters. Per column, the search text means:
     * "." not empty, "_" empty or NULL, "\"text\"" equal to text, "*text*" contains text (spaces included).
     * Otherwise, the words (separated by spaces or commas) are searched with LIKE: "-word" or "!word" must not be
     * contained, "+word" must be contained, and at least one of the other words must be contained. A word or phrase
     * in quotes (\"…\" or '…') is searched as a whole, \"…\" without an operator for equality. "*" is a wildcard,
     * every other character (including "%" and "_") is searched literally.
     *
     * @param array<string, string|int|float|null> $filterArr Column reference => search text. The column reference
     *                                                         is a column name or an SQL expression (e.g.
     *                                                         "CONCAT_WS(' ', a.firstName, a.lastName)") and must
     *                                                         never contain user input.
     *
     * @throws InvalidArgumentException If a column reference is empty or contains a "?".
     */
    public static function createSqlFilters(array $filterArr): DbQueryData
    {
        $whereConditions = [];
        $sqlParams = [];
        foreach ($filterArr as $dataTableReference => $value) {
            $columnFilter = SearchHelper::createColumnFilter(
                column: SearchHelper::checkColumnExpression(column: trim(string: $dataTableReference)),
                value: trim(string: (string) $value),
            );
            if ($columnFilter === null) {
                continue;
            }
            $whereConditions[] = $columnFilter->query;
            $sqlParams = [...$sqlParams, ...$columnFilter->params];
        }
        if (count(value: $whereConditions) === 0) {
            $whereConditions[] = '1=1';
        }

        return new DbQueryData(query: implode(separator: ' AND ', array: $whereConditions), params: $sqlParams);
    }

    private static function checkColumnExpression(string $column): string
    {
        if ($column === '' || str_contains(haystack: $column, needle: '?')) {
            throw new InvalidArgumentException(
                message: 'Invalid column reference "' . $column . '" for the filter. It must not be empty and must not'
                . ' contain a "?", because it is not bound as a parameter.',
            );
        }

        return $column;
    }

    private static function createColumnFilter(string $column, string $value): ?DbQueryData
    {
        if ($value === '') {
            return null;
        }
        if ($value === '.') {
            return new DbQueryData(query: '(' . $column . '!=\'\' AND ' . $column . ' IS NOT NULL)', params: []);
        }
        if ($value === '_') {
            return new DbQueryData(query: '((' . $column . '=\'\') OR (' . $column . ' IS NULL))', params: []);
        }
        if (SearchHelper::isEnclosedIn(value: $value, character: '"')) {
            return new DbQueryData(query: $column . '=?', params: [substr(string: $value, offset: 1, length: -1)]);
        }
        if (SearchHelper::isEnclosedIn(value: $value, character: '*')) {
            return new DbQueryData(
                query: $column . SearchHelper::LIKE_PLACEHOLDER,
                params: [SearchHelper::createLikePattern(searchText: $value)],
            );
        }

        return SearchHelper::createWordsFilter(column: $column, value: $value);
    }

    private static function isEnclosedIn(string $value, string $character): bool
    {
        return mb_strlen(string: $value) > 2
            && substr_count(haystack: $value, needle: $character) === 2
            && str_starts_with(haystack: $value, needle: $character)
            && str_ends_with(haystack: $value, needle: $character);
    }

    private static function createWordsFilter(string $column, string $value): ?DbQueryData
    {
        $conditions = [];
        $params = [];
        $optionalConditions = [];
        $optionalParams = [];
        foreach (SearchHelper::splitFilterWords(value: $value) as $word) {
            if (SearchHelper::isEnclosedIn(value: $word, character: '"')) {
                $conditions[] = $column . '=?';
                $params[] = substr(string: $word, offset: 1, length: -1);
                continue;
            }
            $operator = $word[0];
            $isMandatory = in_array(needle: $operator, haystack: ['!', '-', '+'], strict: true);
            $word = SearchHelper::unquote(word: $isMandatory ? substr(string: $word, offset: 1) : $word);
            if ($word === '') {
                continue;
            }
            if (!$isMandatory) {
                $optionalConditions[] = $column . SearchHelper::LIKE_PLACEHOLDER;
                $optionalParams[] = SearchHelper::createLikePattern(searchText: $word);
                continue;
            }
            $conditions[] = $operator === '+'
                ? $column . SearchHelper::LIKE_PLACEHOLDER
                : '((' . $column . ' NOT' . SearchHelper::LIKE_PLACEHOLDER . ') OR ' . $column . ' IS NULL)';
            $params[] = SearchHelper::createLikePattern(searchText: $word);
        }
        if ($optionalConditions !== []) {
            $conditions[] = '(' . implode(separator: ' OR ', array: $optionalConditions) . ')';
        }

        return $conditions === []
            ? null
            : new DbQueryData(
                query: implode(separator: ' AND ', array: $conditions),
                params: [...$params, ...$optionalParams],
            );
    }

    /**
     * Splits at spaces and commas. A phrase in double or single quotes (optionally after "!", "-" or "+") stays
     * together if the quotes are at word boundaries, so an apostrophe like in "O'Neil" does not start a phrase.
     *
     * @return list<string>
     */
    private static function splitFilterWords(string $value): array
    {
        $words = preg_split(
            pattern: '/(?<=^|[\s,])([-!+]?(?:"[^"]*"|\'[^\']*\'))(?=[\s,]|$)|[\s,]+/',
            subject: $value,
            flags: PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        );

        return $words === false ? [] : $words;
    }

    private static function unquote(string $word): string
    {
        if (strlen(string: $word) < 2) {
            return $word;
        }
        $quote = $word[0];
        if (($quote !== '"' && $quote !== '\'') || !str_ends_with(haystack: $word, needle: $quote)) {
            return $word;
        }

        return substr(string: $word, offset: 1, length: -1);
    }

    /**
     * "*" is a wildcard, everything else is escaped. The pattern matches anywhere unless the search text starts or
     * ends with "*".
     */
    private static function createLikePattern(string $searchText): string
    {
        return (str_starts_with(haystack: $searchText, needle: '*') ? '' : '%')
            . strtr(string: $searchText, from: [...SearchHelper::LIKE_ESCAPE_MAP, '*' => '%'])
            . (str_ends_with(haystack: $searchText, needle: '*') ? '' : '%');
    }

    /**
     * Builds a parameterized "WHERE" condition for a boolean search over one or more fields: every word or
     * "quoted phrase" must be contained in at least one of the fields. Words are combined with OR by default;
     * "and", "or" and "not" (or the shorthands "+word" and "-word") before a word change that. The search is
     * case-insensitive (the words are lowercased). Every character, including "?", "%", "_" and "\", is searched
     * literally.
     *
     * Usage: $dbQuery->addWherePart(wherePart: $data->query, parameters: $data->params);
     *
     * @param string $spaceSeparatedFieldNames Column names, optionally qualified ("table.column") or quoted with
     *                                          backticks. Never pass user input.
     *
     * @throws InvalidArgumentException If no field name is given or a field name is not a valid column name.
     */
    #[NoDiscard]
    public static function createBooleanQuery(string $spaceSeparatedFieldNames, string $queryText): DbQueryData
    {
        $fieldNames = SearchHelper::parseFieldNames(spaceSeparatedFieldNames: $spaceSeparatedFieldNames);
        $terms = strip_tags(string: trim(string: $queryText))
            |> (static fn(string $text): string => mb_strtolower(string: $text, encoding: 'UTF-8'))
            |> SearchHelper::tokenizeSearchText(...)
            |> SearchHelper::parseSearchTerms(...);
        if ($terms === []) {
            return new DbQueryData(query: '1=1', params: []);
        }
        $conditions = '';
        $parameters = [];
        foreach ($terms as $index => $term) {
            $conditions .= ($index === 0 ? '' : $term['operator']->sqlConnector());
            $conditions .= SearchHelper::createWordCondition(
                fieldNames: $fieldNames,
                negated: $term['operator']->isNegated(),
            );
            $likePattern = '%' . strtr(string: $term['word'], from: SearchHelper::LIKE_ESCAPE_MAP) . '%';
            $parameters = [
                ...$parameters,
                ...array_fill(start_index: 0, count: count(value: $fieldNames), value: $likePattern),
            ];
        }

        return new DbQueryData(query: '(' . $conditions . ')', params: $parameters);
    }

    /**
     * @return non-empty-list<string>
     */
    private static function parseFieldNames(string $spaceSeparatedFieldNames): array
    {
        $fieldNames = preg_split(
            pattern: '/\s+/',
            subject: trim(string: $spaceSeparatedFieldNames),
            flags: PREG_SPLIT_NO_EMPTY,
        );
        if ($fieldNames === false || $fieldNames === []) {
            throw new InvalidArgumentException(message: 'At least one field name is required for the boolean search.');
        }
        foreach ($fieldNames as $fieldName) {
            if (preg_match(pattern: SearchHelper::FIELD_NAME_PATTERN, subject: $fieldName) !== 1) {
                throw new InvalidArgumentException(
                    message: 'Invalid field name "' . $fieldName . '" for the boolean search. Use column names like'
                    . ' "name", "table.name" or "`table`.`name`".',
                );
            }
        }

        return $fieldNames;
    }

    /**
     * Splits the text at spaces outside of double quotes. The quotes themselves are removed. "quoted" tells whether
     * the token starts within quotes, so a quoted "and" or "-word" is searched literally.
     *
     * @return list<array{text: string, quoted: bool}>
     */
    private static function tokenizeSearchText(string $text): array
    {
        $tokens = [];
        $buffer = '';
        $startsQuoted = false;
        $insideQuotes = false;
        $length = strlen(string: $text);
        for ($position = 0; $position < $length; $position++) {
            $character = $text[$position];
            if ($character === '"') {
                $insideQuotes = !$insideQuotes;
                continue;
            }
            if ($character === ' ' && !$insideQuotes) {
                $tokens[] = ['text' => $buffer, 'quoted' => $startsQuoted];
                $buffer = '';
                continue;
            }
            if ($buffer === '') {
                $startsQuoted = $insideQuotes;
            }
            $buffer .= $character;
        }
        $tokens[] = ['text' => $buffer, 'quoted' => $startsQuoted];

        return $tokens;
    }

    /**
     * An operator before the first word is searched as a word, an operator without a following word is ignored.
     *
     * @param list<array{text: string, quoted: bool}> $tokens
     *
     * @return list<array{word: string, operator: BooleanSearchOperatorEnum}>
     */
    private static function parseSearchTerms(array $tokens): array
    {
        $terms = [];
        $pendingOperator = null;
        foreach ($tokens as $token) {
            $word = $token['text'];
            if (trim(string: $word) === '') {
                continue;
            }
            if ($terms !== [] && $pendingOperator === null && !$token['quoted']) {
                $pendingOperator = BooleanSearchOperatorEnum::tryFrom(value: trim(string: $word));
                if ($pendingOperator !== null) {
                    continue;
                }
                $pendingOperator = BooleanSearchOperatorEnum::tryFromShorthand(character: $word[0]);
                $word = $pendingOperator === null ? $word : substr(string: $word, offset: 1);
                if (trim(string: $word) === '') {
                    continue;
                }
            }
            $terms[] = ['word' => $word, 'operator' => $pendingOperator ?? BooleanSearchOperatorEnum::OR];
            $pendingOperator = null;
        }

        return $terms;
    }

    /**
     * @param non-empty-list<string> $fieldNames
     */
    private static function createWordCondition(array $fieldNames, bool $negated): string
    {
        $likeConditions = array_map(
            callback: static fn(string $fieldName): string => $fieldName . SearchHelper::LIKE_PLACEHOLDER,
            array: $fieldNames,
        );
        $condition = '(' . implode(separator: ' OR ', array: $likeConditions) . ')';

        return $negated ? '(NOT ' . $condition . ')' : $condition;
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

        $dateFromObj = $this->checkDate(date: $dateFromStr);
        $dateToObj = $this->checkDate(date: $dateToStr);

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

    public function checkDate(string $date): ?DateTime
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

    /**
     * Every word or quoted phrase must be contained in at least one of the columns. "%" and "_" are searched
     * literally.
     *
     * @param list<string> $columns Column names, optionally qualified ("table.column"). They are validated and
     *                              quoted with backticks. Never pass user input.
     *
     * @return array{sql: string, params: list<string>, searchWords: list<string>}
     *
     * @throws InvalidArgumentException If no column is given or a column name is invalid.
     */
    public function createSqlSearch(string $string, array $columns): array
    {
        if ($columns === []) {
            throw new InvalidArgumentException(message: 'At least one column is required for the search.');
        }
        $likeConditions = array_map(
            callback: static fn(string $column): string => SearchHelper::quoteColumnName(column: $column)
                . SearchHelper::LIKE_PLACEHOLDER,
            array: $columns,
        );
        $searchWords = preg_split(
            pattern: "/[\s,]*\"([^\"]+)\"[\s,]*|" . "[\s,]*'([^']+)'[\s,]*|" . "[\s,]+/",
            subject: $string,
            flags: PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        );
        $searchWords = $searchWords === false ? [] : $searchWords;
        $conditions = [];
        $params = [];
        foreach ($searchWords as $searchWord) {
            $conditions[] = '(' . implode(separator: ' OR ', array: $likeConditions) . ')';
            $likePattern = '%' . strtr(string: trim(string: $searchWord), from: SearchHelper::LIKE_ESCAPE_MAP) . '%';
            $params = [...$params, ...array_fill(start_index: 0, count: count(value: $columns), value: $likePattern)];
        }

        return [
            'sql' => $conditions === [] ? '' : '(' . implode(separator: ' AND ', array: $conditions) . ')',
            'params' => $params,
            'searchWords' => $searchWords,
        ];
    }

    private static function quoteColumnName(string $column): string
    {
        if (preg_match(pattern: SearchHelper::FIELD_NAME_PATTERN, subject: $column) !== 1) {
            throw new InvalidArgumentException(
                message: 'Invalid column name "' . $column . '" for the search. Use column names like "name",'
                . ' "table.name" or "`table`.`name`".',
            );
        }

        return implode(
            separator: '.',
            array: array_map(
                callback: static fn(string $part): string => str_starts_with(haystack: $part, needle: '`')
                    ? $part
                    : '`' . $part . '`',
                array: explode(separator: '.', string: $column),
            ),
        );
    }
}
