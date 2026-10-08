<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\api;

/**
 * @internal
 */
enum CurlAuthenticationMethodEnum
{
    case BASIC;
    case BEARER;
}
