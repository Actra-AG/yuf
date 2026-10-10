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
 * The values are private. A data object that is added to another one with `addDataObject()` or
 * `addHtmlDataObjectsArray()` is copied, so later changes of it do not change the object it was added to, and one
 * data object can be added to several others. The template engine gets a new snapshot from `toTemplateData()`.
 *
 * Extension point: a subclass fills its properties in its constructor (see `DetailDataObject`).
 */
class HtmlDataObject
{
    /** @var array<string, bool|HtmlDataObject|list<HtmlDataObject>|string|null> */
    private array $values;

    /**
     * Subclasses call it first in their constructor.
     */
    public function __construct()
    {
        $this->values = [];
    }

    /**
     * @param string|null $text Plain text, escaped here
     */
    public function addText(string $propertyName, ?string $text): void
    {
        $this->values[$propertyName] = $text === null ? null : HtmlEncoder::encode(value: $text);
    }

    /**
     * @param string|null $html Trusted HTML, stored as it is
     */
    public function addHtml(string $propertyName, ?string $html): void
    {
        $this->values[$propertyName] = $html;
    }

    /**
     * @param HtmlText|null $htmlText Rendered here (plain text escaped, HTML as it is)
     */
    public function addHtmlText(string $propertyName, ?HtmlText $htmlText): void
    {
        $this->values[$propertyName] = $htmlText?->render();
    }

    /**
     * Stores a copy of the data object as it is now.
     */
    public function addDataObject(string $propertyName, ?HtmlDataObject $htmlDataObject): void
    {
        $this->values[$propertyName] = $htmlDataObject === null ? null : clone $htmlDataObject;
    }

    /**
     * Stores copies of the data objects as they are now.
     *
     * @param list<HtmlDataObject>|null $htmlDataObjectsArray
     */
    public function addHtmlDataObjectsArray(string $propertyName, ?array $htmlDataObjectsArray): void
    {
        if ($htmlDataObjectsArray === null) {
            $this->values[$propertyName] = null;

            return;
        }

        $copies = [];
        foreach ($htmlDataObjectsArray as $htmlDataObject) {
            $copies[] = clone $htmlDataObject;
        }

        $this->values[$propertyName] = $copies;
    }

    public function addBooleanValue(string $propertyName, bool $booleanValue): void
    {
        $this->values[$propertyName] = $booleanValue;
    }

    public function addNullValue(string $propertyName): void
    {
        $this->values[$propertyName] = null;
    }

    /**
     * @return stdClass A new object with the properties (nested data objects as `stdClass`, lists as arrays); changing
     *                  it does not change this data object
     */
    public function toTemplateData(): stdClass
    {
        $data = new stdClass();
        foreach ($this->values as $propertyName => $value) {
            $data->{$propertyName} = match (true) {
                $value instanceof HtmlDataObject => $value->toTemplateData(),
                is_array(value: $value) => array_map(
                    callback: static fn(HtmlDataObject $htmlDataObject): stdClass => $htmlDataObject->toTemplateData(),
                    array: $value,
                ),
                default => $value,
            };
        }

        return $data;
    }
}
