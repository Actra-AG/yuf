<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

enum ProtocolEnum: string
{
    case HTTP = 'http';
    case HTTPS = 'https';
}
