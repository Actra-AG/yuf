<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\db;

enum StatusEnum: string
{
    case Active = 'active';
    case Blocked = 'blocked';
}
