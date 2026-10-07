<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form;

/**
 * What a request parameter looks like, independent of the field that reads it.
 */
enum InputShapeEnum
{
    case MISSING;
    case TEXT;
    case LIST;
    case INVALID;
}
