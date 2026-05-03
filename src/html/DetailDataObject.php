<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

use actra\yuf\html\HtmlDataObject;

class DetailDataObject extends HtmlDataObject
{
    public function __construct(
        string $name,
        string $value,
        bool $isEncodedForRendering
    ) {
        parent::__construct();
        $this->addTextElement(
            propertyName: 'name',
            content: $name,
            isEncodedForRendering: true
        );
        $this->addTextElement(
            propertyName: 'value',
            content: $value,
            isEncodedForRendering: $isEncodedForRendering
        );
    }
}