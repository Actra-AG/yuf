<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\layout;

enum CheckboxOptionsLayout: int
{
    case NONE = 0;
    case DEFINITION_LIST = 1;
    case LEGEND_AND_LIST = 2;
    case CHECKBOX_ITEM = 3;
}