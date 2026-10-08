<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

class DetailDataObject extends HtmlDataObject
{
    public function __construct(
        string $name,
        string $value,
        bool $isHtml,
    ) {
        parent::__construct();
        $this->addHtml(propertyName: 'name', html: $name);
        if ($isHtml) {
            $this->addHtml(propertyName: 'value', html: $value);
        } else {
            $this->addText(propertyName: 'value', text: $value);
        }
    }
}
