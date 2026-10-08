<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

/**
 * The parts of a query which `DbQuery` splits a given query into.
 *
 * @internal
 */
enum DbQuerySectionEnum: string
{
    case SELECT = 'SELECT';
    case FROM = 'FROM';
    case WHERE = 'WHERE';
}
