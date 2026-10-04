<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

/**
 * Operators of the boolean search text understood by SearchHelper::createBooleanQuery().
 */
enum BooleanSearchOperatorEnum: string
{
    case AND = 'and';
    case OR = 'or';
    case NOT = 'not';

    /**
     * "+word" means "and word", "-word" means "and not word".
     */
    public static function tryFromShorthand(string $character): ?BooleanSearchOperatorEnum
    {
        return match ($character) {
            '+' => BooleanSearchOperatorEnum::AND,
            '-' => BooleanSearchOperatorEnum::NOT,
            default => null,
        };
    }

    public function sqlConnector(): string
    {
        return match ($this) {
            BooleanSearchOperatorEnum::AND, BooleanSearchOperatorEnum::NOT => ' AND ',
            BooleanSearchOperatorEnum::OR => ' OR ',
        };
    }

    public function isNegated(): bool
    {
        return $this === BooleanSearchOperatorEnum::NOT;
    }
}