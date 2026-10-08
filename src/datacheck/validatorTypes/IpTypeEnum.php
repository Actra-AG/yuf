<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\datacheck\validatorTypes;

enum IpTypeEnum
{
    case IP;
    case IPV4;
    case IPV6;
}
