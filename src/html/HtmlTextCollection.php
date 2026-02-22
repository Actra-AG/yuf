<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

class HtmlTextCollection
{
    /** @var HtmlText[] */
    private(set) array $items = [];

    /**
     * @param HtmlText[] $items
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