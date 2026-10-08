<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

use stdClass;

/**
 * The properties of one item that a template reads with selectors (`item.name`). Text is made safe when it is added:
 * `addText()` escapes it, `addHtml()` takes HTML that the application built itself and stores it as it is; the
 * template engine outputs the stored strings as they are.
 *
 * Extension point: a subclass fills its properties in its constructor (see `DetailDataObject`).
 */
class HtmlDataObject
{
    public private(set) stdClass $data;

    public function __construct()
    {
        $this->data = new stdClass();
    }

    /**
     * @param string|null $text Plain text, escaped here
     */
    public function addText(string $propertyName, ?string $text): void
    {
        $this->data->{$propertyName} = $text === null ? null : HtmlEncoder::encode(value: $text);
    }

    /**
     * @param string|null $html Trusted HTML, stored as it is
     */
    public function addHtml(string $propertyName, ?string $html): void
    {
        $this->data->{$propertyName} = $html;
    }

    public function addDataObject(string $propertyName, ?HtmlDataObject $htmlDataObject): void
    {
        $this->data->{$propertyName} = $htmlDataObject === null ? null : $htmlDataObject->data;
    }

    /**
     * @param list<HtmlDataObject>|null $htmlDataObjectsArray
     */
    public function addHtmlDataObjectsArray(string $propertyName, ?array $htmlDataObjectsArray): void
    {
        if ($htmlDataObjectsArray === null) {
            $this->data->{$propertyName} = null;

            return;
        }

        $array = [];
        foreach ($htmlDataObjectsArray as $htmlDataObject) {
            $array[] = $htmlDataObject->data;
        }

        $this->data->{$propertyName} = $array;
    }

    public function addBooleanValue(string $propertyName, bool $booleanValue): void
    {
        $this->data->{$propertyName} = $booleanValue;
    }

    public function addNullValue(string $propertyName): void
    {
        $this->data->{$propertyName} = null;
    }
}
