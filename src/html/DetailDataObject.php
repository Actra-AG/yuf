<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

/**
 * A label with a value for detail lists. Both are `HtmlText`: plain text (`HtmlText::fromText()`) is escaped, trusted
 * HTML (`HtmlText::fromHtml()`) stays as it is.
 */
final class DetailDataObject extends HtmlDataObject
{
    public function __construct(
        HtmlText $name,
        HtmlText $value,
    ) {
        parent::__construct();
        $this->addHtml(propertyName: 'name', html: $name->render());
        $this->addHtml(propertyName: 'value', html: $value->render());
    }
}
