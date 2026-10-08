<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

/**
 * A list of texts for a `for` loop of a template.
 */
final class HtmlTextCollection
{
    /** @var list<HtmlText> */
    public private(set) array $items = [];

    /**
     * @param list<HtmlText> $items
     */
    public function __construct(array $items = [])
    {
        foreach ($items as $item) {
            $this->add(htmlText: $item);
        }
    }

    public function add(HtmlText $htmlText): void
    {
        $this->items[] = $htmlText;
    }
}
