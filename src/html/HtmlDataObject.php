<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

use stdClass;

class HtmlDataObject
{
    public private(set) stdClass $data;

    public function __construct()
    {
        $this->data = new stdClass();
    }

    public function addTextElement(string $propertyName, ?string $content, bool $isEncodedForRendering): void
    {
        if ($content === null) {
            $this->data->{$propertyName} = null;

            return;
        }

        $this->data->{$propertyName} = $isEncodedForRendering ? $content : HtmlEncoder::encode(value: $content);
    }

    public function addDataObject(string $propertyName, ?HtmlDataObject $htmlDataObject): void
    {
        $this->data->{$propertyName} = $htmlDataObject === null ? null : $htmlDataObject->data;
    }

    /**
     * @param HtmlDataObject[]|null $htmlDataObjectsArray
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
