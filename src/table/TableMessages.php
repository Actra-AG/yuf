<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table;

/**
 * The texts that `SmartTable` and `DbResultTable` create themselves. Plain text, encoded when it is rendered. The
 * defaults are German; `TableMessages::english()`, `french()` and `italian()` have the English, French and Italian
 * ones. Pass it to `SmartTable(messages: ...)` or `DbResultTable(messages: ...)`.
 *
 * `[amount]` in `oneResult` and `numResults` is replaced with the amount, which is output in `<strong>` (the amount of
 * `numResults` with the apostrophe as thousands separator).
 */
final readonly class TableMessages
{
    public const string AMOUNT_PLACEHOLDER = '[amount]';

    public function __construct(
        public string $noData = 'Es wurden keine Einträge gefunden.',
        public string $oneResult = 'Es wurde [amount] Resultat gefunden.',
        public string $numResults = 'Es wurden [amount] Resultate gefunden.',
    ) {}

    public static function english(): TableMessages
    {
        return new TableMessages(
            noData: 'No entries found.',
            oneResult: '[amount] result found.',
            numResults: '[amount] results found.',
        );
    }

    public static function french(): TableMessages
    {
        return new TableMessages(
            noData: 'Aucune entrée trouvée.',
            oneResult: '[amount] résultat trouvé.',
            numResults: '[amount] résultats trouvés.',
        );
    }

    public static function italian(): TableMessages
    {
        return new TableMessages(
            noData: 'Nessuna voce trovata.',
            oneResult: '[amount] risultato trovato.',
            numResults: '[amount] risultati trovati.',
        );
    }
}
