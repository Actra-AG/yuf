<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

/**
 * Where an input value of a request comes from: the query string (`$_GET`) or the form data of the body (`$_POST`).
 */
enum InputSourceEnum: string
{
    case QUERY = 'query';
    case POST = 'post';
}
