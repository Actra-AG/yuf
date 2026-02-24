<?php
/**
 * @author    Christof Moser <contact@actra.ch>
 * @copyright Actra AG, Embrach, Switzerland, www.actra.ch
 * @license   MIT
 */

namespace actra\yuf\form\component\layout;

enum RadioOptionsLayout: int
{
    case NONE = 0;
    case DEFINITION_LIST = 1;
    case LEGEND_AND_LIST = 2;
}