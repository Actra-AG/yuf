<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\column;

use IntlDateFormatter;

/**
 * How much of a date or time a locale-aware column writes (the styles of `IntlDateFormatter`), e.g. for `de_CH`:
 * `SHORT` 05.10.26, `MEDIUM` 05.10.2026, `LONG` 5. Oktober 2026, `FULL` Montag, 5. Oktober 2026.
 */
enum DateStyleEnum: int
{
    case NONE = IntlDateFormatter::NONE;
    case SHORT = IntlDateFormatter::SHORT;
    case MEDIUM = IntlDateFormatter::MEDIUM;
    case LONG = IntlDateFormatter::LONG;
    case FULL = IntlDateFormatter::FULL;
}
