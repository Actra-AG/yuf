<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\datacheck\validatorTypes;

enum IpTypeEnum
{
    case ip;
    case ipv4;
    case ipv6;
}