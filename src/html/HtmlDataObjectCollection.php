<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

class HtmlDataObjectCollection
{
    /** @var HtmlDataObject[] */
    private(set) array $items = [];

    public function add(HtmlDataObject $htmlDataObject): void
    {
        $this->items[] = $htmlDataObject;
    }
}