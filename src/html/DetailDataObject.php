<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

/**
 * A label with a value for detail lists. The label is trusted HTML and is never escaped; the value is escaped unless
 * `$isHtml` is `true`.
 */
final class DetailDataObject extends HtmlDataObject
{
    /**
     * @param string $name Trusted HTML, stored as it is
     * @param string $value Plain text (escaped here) or, with `$isHtml`, trusted HTML
     */
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
