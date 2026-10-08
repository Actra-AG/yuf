<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

/**
 * A list of items for a `for` loop of a template.
 */
final class HtmlDataObjectCollection
{
    /** @var list<HtmlDataObject> */
    public private(set) array $items = [];

    public function add(HtmlDataObject $htmlDataObject): void
    {
        $this->items[] = $htmlDataObject;
    }
}
