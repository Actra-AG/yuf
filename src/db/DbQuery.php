<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

use LogicException;
use stdClass;

/**
 * Represents a "SELECT ... FROM ... [JOIN ...] [WHERE ...]" query which can be extended dynamically
 * (additional joins, conditions, and sorting) before it gets executed.
 *
 * The query given to createFromSqlQuery() is split into its sections by a simple, whitespace-based
 * tokenizer. Therefore, it must not contain string literals with parentheses, and every placeholder
 * within it must be a positional "?" placeholder.
 *
 * @see DbQueryData The readonly result of getDbQueryData(), within the same namespace actra\yuf\db.
 */
class DbQuery
{
    public const string SORT_ASC = 'ASC';
    public const string SORT_DESC = 'DESC';

    private const string SECTION_SELECT = 'SELECT';
    private const string SECTION_FROM = 'FROM';
    private const string SECTION_JOIN = 'JOIN';
    private const string SECTION_WHERE = 'WHERE';

    /** Keywords that introduce the join itself. */
    private const array JOIN_KEYWORDS = ['join', 'straight_join'];
    /** Keywords that may precede the join keyword, e.g. "LEFT OUTER JOIN". */
    private const array JOIN_MODIFIER_KEYWORDS = ['left', 'right', 'full', 'inner', 'outer', 'cross', 'natural'];
    /** Keywords for clauses which are not supported because they would break the generated queries. */
    private const array UNSUPPORTED_KEYWORDS = ['group', 'having', 'order', 'limit', 'union'];

    /** @var string[] Tokens of the SELECT section. */
    private array $selectParts = [];
    /** @var string[] Tokens of the FROM section, without the joins. */
    private array $fromParts = [];
    /** @var string[] One complete join clause (e.g. "LEFT JOIN t ON t.id = x.id") per entry. */
    private array $joinParts = [];
    /** @var string[] One complete condition per entry; the conditions are combined with "AND". */
    private array $whereParts = [];
    /** @var string[] One complete "ORDER BY" entry, including its sort direction, per entry. */
    private array $orderParts = [];
    /** Parameters, stored per section, because sections can be extended after the query was created. */
    private array $selectParameters = [];
    private array $fromParameters = [];
    private array $joinParameters = [];
    private array $whereParameters = [];
    private array $orderParameters = [];

    private function __construct()
    {
    }

    public static function createFromSqlQuery(string $query, array $parameters = []): DbQuery
    {
        $sectionTokens = DbQuery::splitIntoSections(tokens: DbQuery::tokenize(query: $query));

        $dbQuery = new DbQuery();
        $dbQuery->selectParts = $sectionTokens[DbQuery::SECTION_SELECT];
        [$dbQuery->fromParts, $dbQuery->joinParts] = DbQuery::splitOffJoinClauses(
            tokens: $sectionTokens[DbQuery::SECTION_FROM]
        );
        if (count(value: $sectionTokens[DbQuery::SECTION_WHERE]) > 0) {
            $dbQuery->whereParts[] = implode(separator: ' ', array: $sectionTokens[DbQuery::SECTION_WHERE]);
        }

        // The parameters are given as one flat list, but they must be kept per section because every
        // section can be extended with additional parameters afterward.
        $dbQuery->selectParameters = DbQuery::extractParameters(
            parameters: $parameters,
            queryParts: $dbQuery->selectParts,
            section: DbQuery::SECTION_SELECT
        );
        $dbQuery->fromParameters = DbQuery::extractParameters(
            parameters: $parameters,
            queryParts: $dbQuery->fromParts,
            section: DbQuery::SECTION_FROM
        );
        $dbQuery->joinParameters = DbQuery::extractParameters(
            parameters: $parameters,
            queryParts: $dbQuery->joinParts,
            section: DbQuery::SECTION_JOIN
        );
        $dbQuery->whereParameters = DbQuery::extractParameters(
            parameters: $parameters,
            queryParts: $dbQuery->whereParts,
            section: DbQuery::SECTION_WHERE
        );
        if (count(value: $parameters) > 0) {
            throw new LogicException(
                message: 'There are more parameters than "?" placeholders within the query.'
            );
        }

        return $dbQuery;
    }

    /**
     * Splits the tokens into the SELECT, FROM (joins included) and WHERE sections. Sub queries are
     * collected into a single token each, so their content does not influence the sections.
     *
     * @param string[] $tokens
     *
     * @return array<string, string[]>
     */
    private static function splitIntoSections(array $tokens): array
    {
        $sectionTokens = [
            DbQuery::SECTION_SELECT => [],
            DbQuery::SECTION_FROM => [],
            DbQuery::SECTION_WHERE => [],
        ];
        $section = null;
        $currentSubQueryLevel = 0;
        $subQueryTokens = [];

        foreach ($tokens as $token) {
            if ($token === '(') {
                if ($section === null) {
                    throw new LogicException(message: '( is not allowed before the first SELECT part.');
                }
                $currentSubQueryLevel++;
                $subQueryTokens[$currentSubQueryLevel] = [$token];
                continue;
            }
            if ($currentSubQueryLevel > 0) {
                $subQueryTokens[$currentSubQueryLevel][] = $token;
                if ($token !== ')') {
                    continue;
                }
                $token = implode(separator: ' ', array: $subQueryTokens[$currentSubQueryLevel]);
                unset($subQueryTokens[$currentSubQueryLevel]);
                $currentSubQueryLevel--;
                if ($currentSubQueryLevel > 0) {
                    $subQueryTokens[$currentSubQueryLevel][] = $token;
                    continue;
                }
            } elseif ($token === ')') {
                throw new LogicException(message: ') is not allowed if not part of a sub query.');
            }

            $lowercaseToken = strtolower(string: $token);
            if ($lowercaseToken === 'select') {
                if ($section !== null) {
                    throw new LogicException(
                        message: '"SELECT" is not allowed if already in "SELECT", "FROM" or "WHERE".'
                    );
                }
                $section = DbQuery::SECTION_SELECT;
                continue;
            }
            if ($lowercaseToken === 'from') {
                if ($section !== DbQuery::SECTION_SELECT) {
                    throw new LogicException(message: '"FROM" must be after "SELECT".');
                }
                $section = DbQuery::SECTION_FROM;
                continue;
            }
            if ($lowercaseToken === 'where') {
                if ($section !== DbQuery::SECTION_FROM) {
                    throw new LogicException(message: '"WHERE" must be after "FROM".');
                }
                $section = DbQuery::SECTION_WHERE;
                continue;
            }
            if (in_array(needle: $lowercaseToken, haystack: DbQuery::UNSUPPORTED_KEYWORDS, strict: true)) {
                throw new LogicException(
                    message: '"' . strtoupper(string: $lowercaseToken) . '" is not supported within the query.'
                );
            }
            if ($section === null) {
                throw new LogicException(message: 'You are not within "SELECT", "FROM" or "WHERE"');
            }
            $sectionTokens[$section][] = $token;
        }

        if ($currentSubQueryLevel > 0) {
            throw new LogicException(message: 'There is at least one sub query which is not closed by ")".');
        }
        if (count(value: $sectionTokens[DbQuery::SECTION_SELECT]) === 0) {
            throw new LogicException(message: 'The query does not contain any "SELECT" part.');
        }
        if (count(value: $sectionTokens[DbQuery::SECTION_FROM]) === 0) {
            throw new LogicException(message: 'The query does not contain any "FROM" part.');
        }

        return $sectionTokens;
    }

    /**
     * Splits the tokens of the FROM section into the tokens before the first join and one string per
     * complete join clause (e.g. "LEFT OUTER JOIN t ON t.id = x.id").
     *
     * @param string[] $tokens
     *
     * @return array{0: string[], 1: string[]} The FROM tokens and the join clauses
     */
    private static function splitOffJoinClauses(array $tokens): array
    {
        $fromParts = [];
        $joinParts = [];

        foreach ($tokens as $index => $token) {
            if (DbQuery::isStartOfJoinClause(tokens: $tokens, index: $index)) {
                $joinParts[] = $token;
                continue;
            }
            if (DbQuery::isJoinToken(token: $token) && !DbQuery::isJoinModifier(token: $tokens[$index - 1] ?? '')) {
                // A join keyword which neither starts a clause nor continues one like the "OUTER" of
                // "LEFT OUTER JOIN" can only be a clause without its "JOIN" keyword.
                throw new LogicException(message: 'There is an incomplete join clause at "' . $token . '".');
            }
            if (count(value: $joinParts) === 0) {
                $fromParts[] = $token;
                continue;
            }
            $joinParts[array_key_last(array: $joinParts)] .= ' ' . $token;
        }

        return [$fromParts, $joinParts];
    }

    /**
     * A join clause starts at the first of its optional modifiers ("LEFT", "OUTER", ...) which are
     * followed by the join keyword itself. Therefore, this only depends on the surrounding tokens and
     * not on any parsing state.
     *
     * @param string[] $tokens
     */
    private static function isStartOfJoinClause(array $tokens, int $index): bool
    {
        if (!isset($tokens[$index])) {
            return false;
        }
        if (DbQuery::isJoinModifier(token: $tokens[$index - 1] ?? '')) {
            // The clause has already been started by one of the previous modifiers.
            return false;
        }
        while (isset($tokens[$index]) && DbQuery::isJoinModifier(token: $tokens[$index])) {
            $index++;
        }
        if (!isset($tokens[$index])) {
            // The modifiers are not followed by a join keyword, so no clause starts here.
            return false;
        }

        return in_array(
            needle: strtolower(string: $tokens[$index]),
            haystack: DbQuery::JOIN_KEYWORDS,
            strict: true
        );
    }

    private static function isJoinModifier(string $token): bool
    {
        return in_array(
            needle: strtolower(string: $token),
            haystack: DbQuery::JOIN_MODIFIER_KEYWORDS,
            strict: true
        );
    }

    private static function isJoinToken(string $token): bool
    {
        $lowercaseToken = strtolower(string: $token);

        return in_array(needle: $lowercaseToken, haystack: DbQuery::JOIN_KEYWORDS, strict: true)
            || in_array(needle: $lowercaseToken, haystack: DbQuery::JOIN_MODIFIER_KEYWORDS, strict: true);
    }

    /**
     * @return string[]
     */
    private static function tokenize(string $query): array
    {
        $normalizedQuery = DbQuery::normalizeWhitespace(
            queryPart: str_replace(
                search: ['(', ')'],
                replace: [' ( ', ' ) '],
                subject: $query
            )
        );
        if ($normalizedQuery === '') {
            throw new LogicException(message: 'The query must not be empty.');
        }

        return explode(separator: ' ', string: $normalizedQuery);
    }

    /**
     * Replaces every sequence of whitespace (including line breaks and indentation of multi-line
     * query parts) by a single space.
     */
    private static function normalizeWhitespace(string $queryPart): string
    {
        return trim(
            string: preg_replace(
                pattern: '!\s+!',
                replacement: ' ',
                subject: $queryPart
            )
        );
    }

    /**
     * Removes the parameters which belong to the given query parts from the beginning of the given
     * parameter list and returns them.
     */
    private static function extractParameters(array &$parameters, array $queryParts, string $section): array
    {
        $amountOfPlaceholders = substr_count(
            haystack: implode(separator: ' ', array: $queryParts),
            needle: '?'
        );
        if (count(value: $parameters) < $amountOfPlaceholders) {
            throw new LogicException(
                message: 'There are not enough parameters for the "?" placeholders within the "' . $section . '" part.'
            );
        }

        return array_splice(array: $parameters, offset: 0, length: $amountOfPlaceholders);
    }

    /**
     * @param FrameworkDB $db
     * @param int $offset
     * @param int $rowCount
     *
     * @return stdClass[]
     */
    public function selectFromDb(FrameworkDB $db, int $offset, int $rowCount): array
    {
        $dbQueryData = $this->getDbQueryData(
            offset: $offset,
            rowCount: $rowCount
        );

        return $db->select(
            sql: $dbQueryData->query,
            parameters: $dbQueryData->params
        );
    }

    public function getDbQueryData(int $offset, int $rowCount): DbQueryData
    {
        $queryParts = [
            'SELECT',
            ...$this->selectParts,
            ...$this->getFromJoinAndWhereParts(),
        ];
        if (count(value: $this->orderParts) > 0) {
            $queryParts[] = 'ORDER BY ' . implode(separator: ', ', array: $this->orderParts);
        }
        $queryParts[] = 'LIMIT ?, ?';
        $query = DbQuery::buildQuery(queryParts: $queryParts);
        $parameters = [
            ...$this->selectParameters,
            ...$this->getFromJoinAndWhereParameters(),
            ...$this->orderParameters,
            $offset,
            $rowCount,
        ];
        DbQuery::checkParameterCount(queryPart: $query, parameters: $parameters);

        return new DbQueryData(
            query: $query,
            params: $parameters
        );
    }

    /**
     * Counts the rows the query would return without its "LIMIT".
     *
     * The columns of the "SELECT" are replaced by "COUNT(*)", so the parameters which belong to them
     * (e.g. those of a sub query within the selected columns) are intentionally not bound: their
     * placeholders are not part of the generated count query. The same applies to the parameters of a
     * sorting expression, because a count does not need an "ORDER BY". Parameters within a sub query
     * of the "FROM" or "JOIN" parts and those of the "WHERE" part are kept, as their placeholders
     * remain within the query.
     */
    public function getTotalAmount(FrameworkDB $db): int
    {
        $query = DbQuery::buildQuery(
            queryParts: [
                'SELECT COUNT(*) AS amount',
                ...$this->getFromJoinAndWhereParts(),
            ]
        );
        $parameters = $this->getFromJoinAndWhereParameters();
        DbQuery::checkParameterCount(queryPart: $query, parameters: $parameters);

        $result = $db->select(
            sql: $query,
            parameters: $parameters
        );

        return (int)$result[0]->amount;
    }

    /**
     * @return string[]
     */
    private function getFromJoinAndWhereParts(): array
    {
        $queryParts = [
            'FROM',
            ...$this->fromParts,
            ...$this->joinParts,
        ];
        if (count(value: $this->whereParts) > 0) {
            $queryParts[] = 'WHERE';
            // Each condition is wrapped, so an "OR" within one of them cannot change the meaning of the others.
            $queryParts[] = count(value: $this->whereParts) === 1
                ? $this->whereParts[0]
                : '(' . implode(separator: ') AND (', array: $this->whereParts) . ')';
        }

        return $queryParts;
    }

    private function getFromJoinAndWhereParameters(): array
    {
        return [
            ...$this->fromParameters,
            ...$this->joinParameters,
            ...$this->whereParameters,
        ];
    }

    private static function buildQuery(array $queryParts): string
    {
        return str_replace(
            search: ' (',
            replace: '(',
            subject: implode(separator: ' ', array: $queryParts)
        );
    }

    public function addJoinPart(string $joinPart, array $parameters): void
    {
        $joinPart = DbQuery::normalizeWhitespace(queryPart: $joinPart);
        if (
            preg_match(
                pattern: '!(^|\s)(' . implode(separator: '|', array: DbQuery::JOIN_KEYWORDS) . ')\s!i',
                subject: $joinPart
            ) !== 1
        ) {
            throw new LogicException(
                message: 'The join part must contain the complete JOIN clause, e.g. "LEFT JOIN t ON t.id = x.id".'
            );
        }
        DbQuery::checkParameterCount(queryPart: $joinPart, parameters: $parameters);

        $this->joinParts[] = $joinPart;
        $this->joinParameters = [...$this->joinParameters, ...array_values(array: $parameters)];
    }

    public function addWherePart(string $wherePart, array $parameters): void
    {
        $wherePart = DbQuery::normalizeWhitespace(queryPart: $wherePart);
        if ($wherePart === '') {
            throw new LogicException(message: 'The where part must not be empty.');
        }
        DbQuery::checkParameterCount(queryPart: $wherePart, parameters: $parameters);

        $this->whereParts[] = $wherePart;
        $this->whereParameters = [...$this->whereParameters, ...array_values(array: $parameters)];
    }

    private static function checkParameterCount(string $queryPart, array $parameters): void
    {
        $amountOfPlaceholders = substr_count(haystack: $queryPart, needle: '?');
        $amountOfParameters = count(value: $parameters);
        if ($amountOfPlaceholders !== $amountOfParameters) {
            throw new LogicException(
                message: 'The amount of parameters (' . $amountOfParameters . ') does not match the amount of "?"'
                . ' placeholders (' . $amountOfPlaceholders . ') in "' . $queryPart . '".'
            );
        }
    }

    /**
     * Without $parameters, $column is a column name or a list of column names separated by a comma
     * (e.g. "a.name, b.name"); every one of them is validated, escaped and sorted in the given
     * direction.
     *
     * With $parameters, $column is an SQL expression whose values are bound as "?" placeholders
     * (e.g. "MATCH(t.searchContent) AGAINST (? IN BOOLEAN MODE)"). Such an expression is taken over
     * unchanged and must therefore never contain user input.
     */
    public function addOrderPart(string $column, array $parameters = [], bool $ascending = true): void
    {
        $sortDirection = $ascending ? DbQuery::SORT_ASC : DbQuery::SORT_DESC;
        if (count(value: $parameters) === 0) {
            foreach (explode(separator: ',', string: $column) as $singleColumn) {
                $this->orderParts[] = DbQuery::escapeColumn(column: $singleColumn) . ' ' . $sortDirection;
            }

            return;
        }

        $expression = DbQuery::normalizeWhitespace(queryPart: $column);
        if ($expression === '') {
            throw new LogicException(message: 'The order expression must not be empty.');
        }
        if (
            preg_match(
                pattern: '!\s(' . DbQuery::SORT_ASC . '|' . DbQuery::SORT_DESC . ')$!i',
                subject: $expression
            ) === 1
        ) {
            throw new LogicException(
                message: 'The order expression "' . $expression . '" must not contain the sort direction;'
                . ' it is added according to the "ascending" argument.'
            );
        }
        DbQuery::checkParameterCount(queryPart: $expression, parameters: $parameters);

        $this->orderParts[] = $expression . ' ' . $sortDirection;
        $this->orderParameters = [...$this->orderParameters, ...array_values(array: $parameters)];
    }

    /**
     * Removes all sorting which has been added so far, e.g. to let an explicitly requested sorting
     * replace a default one instead of being appended to it.
     */
    public function clearOrderParts(): void
    {
        $this->orderParts = [];
        $this->orderParameters = [];
    }

    /**
     * The column of an "ORDER BY" cannot be bound as a "?" placeholder, so it is validated against a
     * whitelist of characters which are valid within an identifier. Every part of a qualified column
     * is wrapped in backticks (e.g. "t.group" => "`t`.`group`"), because it could be a reserved word.
     */
    private static function escapeColumn(string $column): string
    {
        $column = trim(string: $column);
        if ($column === '') {
            throw new LogicException(message: 'The order column must not be empty.');
        }
        // Prevent SQL injection, because the column cannot be bound as a parameter.
        if (preg_match(pattern: '/[^a-zA-Z0-9_.`]/', subject: $column) === 1) {
            throw new LogicException(message: 'Invalid characters in order column "' . $column . '".');
        }
        // Existing backticks are removed first to prevent double-escaping.
        $identifierParts = explode(
            separator: '.',
            string: str_replace(search: '`', replace: '', subject: $column)
        );
        foreach ($identifierParts as $identifierPart) {
            if (trim(string: $identifierPart) === '') {
                throw new LogicException(message: 'Incomplete order column "' . $column . '".');
            }
        }

        return '`' . implode(separator: '`.`', array: $identifierParts) . '`';
    }
}
