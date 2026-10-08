<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

use DOMElement;
use InvalidArgumentException;
use LogicException;
use Override;
use RuntimeException;
use SimpleXMLElement;
use stdClass;

/**
 * A SimpleXMLElement that writes text values as CDATA sections and can add arrays and other XML trees.
 */
final class SimpleXmlExtended extends SimpleXMLElement
{
    /**
     * Converts XML to a nested array for standardized output: attributes below `@attributes`, repeated elements as
     * list, empty elements below the root as empty string. The XML is parsed without loading external entities or
     * network resources.
     *
     * @param string $xml XML as string, which will be converted
     *
     * @return false|array<array-key, mixed> The (nested) array, "false" if the XML is invalid
     */
    public static function convertXmlToArray(string $xml): false|array
    {
        $previousUseInternalErrors = libxml_use_internal_errors(use_errors: true);
        try {
            $simpleXmlElement = simplexml_load_string(
                data: $xml,
                class_name: SimpleXMLElement::class,
                options: LIBXML_NOCDATA | LIBXML_NONET,
            );
            libxml_clear_errors();
        } finally {
            libxml_use_internal_errors(use_errors: $previousUseInternalErrors);
        }
        if ($simpleXmlElement === false) {
            return false;
        }
        $array = JsonUtils::decodeJsonString(
            jsonString: JsonUtils::convertToJsonString(valueToConvert: (array) $simpleXmlElement),
            returnAssociativeArray: true,
        );

        return SimpleXmlExtended::replaceEmptyChildArrays(data: is_array(value: $array) ? $array : []);
    }

    public function remove(SimpleXMLElement $node): void
    {
        $domNode = dom_import_simplexml(node: $node);
        $domNode->parentNode?->removeChild($domNode);
    }

    /**
     * Adds every entry of the array as child element with the value as CDATA section, the arrays and objects
     * below it as nested elements. Numeric keys become `item<key>`.
     *
     * @param array<array-key, mixed>|stdClass $array The values are text, numbers, booleans, `null`, arrays and objects
     * @param ?SimpleXMLElement $xml The element to add to, this element if `null`
     * @param bool $includeNull `false` leaves out the entries with the value `null`
     *
     * @throws InvalidArgumentException If a value cannot be written as text (e.g. a resource)
     */
    public function addArray(array|stdClass $array, ?SimpleXMLElement $xml = null, bool $includeNull = true): void
    {
        $parent = $xml ?? $this;
        $entries = $array instanceof stdClass ? get_object_vars(object: $array) : $array;
        foreach ($entries as $key => $value) {
            if (!$includeNull && $value === null) {
                continue;
            }
            $name = is_numeric(value: $key) ? 'item' . $key : $key;
            $child = $parent->addChild(qualifiedName: $name);
            if ($child === null) {
                throw new InvalidArgumentException(message: 'The element "' . $name . '" cannot be added.');
            }
            if ($value instanceof stdClass || is_array(value: $value)) {
                $this->addArray(array: $value, xml: $child, includeNull: $includeNull);
                continue;
            }
            if ($value !== null && !is_scalar(value: $value)) {
                throw new InvalidArgumentException(
                    message: 'The value of "' . $name . '" must be text, a number, a boolean, null, an array or an'
                    . ' object, ' . get_debug_type(value: $value) . ' given.',
                );
            }
            SimpleXmlExtended::appendCdata(element: $child, text: (string) $value);
        }
    }

    /**
     * @param ?string $value Written as CDATA section
     */
    #[Override]
    public function addChild(string $qualifiedName, ?string $value = null, ?string $namespace = null): ?static
    {
        $newChild = parent::addChild(qualifiedName: $qualifiedName, value: null, namespace: $namespace);
        if ($newChild !== null && $value !== null) {
            SimpleXmlExtended::appendCdata(element: $newChild, text: $value);
        }

        return $newChild;
    }

    public function addCdata(string|int|float|bool|null $cdataText): void
    {
        SimpleXmlExtended::appendCdata(element: $this, text: (string) $cdataText);
    }

    /**
     * Appends another XML tree to this element.
     *
     * Inspired by http://stackoverflow.com/questions/3418019/simplexml-append-one-tree-to-another
     */
    public function addXml(SimpleXMLElement $xmlToAppend): void
    {
        $parent = SimpleXmlExtended::importDomElement(element: $this);
        $child = SimpleXmlExtended::importDomElement(element: $xmlToAppend);
        $document = $parent->ownerDocument ?? throw new LogicException(message: 'The element has no document.');
        $parent->appendChild(node: $document->importNode(node: $child, deep: true));
    }

    /**
     * Empty arrays below the root (an element without content) become an empty string.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private static function replaceEmptyChildArrays(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array(value: $value)) {
                $data[$key] = $value === [] ? '' : SimpleXmlExtended::replaceEmptyChildArrays(data: $value);
            }
        }

        return $data;
    }

    private static function appendCdata(SimpleXMLElement $element, string $text): void
    {
        $domElement = SimpleXmlExtended::importDomElement(element: $element);
        $document = $domElement->ownerDocument ?? throw new LogicException(message: 'The element has no document.');
        $cdataSection = $document->createCDATASection(data: $text);
        if ($cdataSection === false) {
            throw new RuntimeException(message: 'The CDATA section cannot be created.');
        }
        $domElement->appendChild(node: $cdataSection);
    }

    private static function importDomElement(SimpleXMLElement $element): DOMElement
    {
        $domNode = dom_import_simplexml(node: $element);
        if (!$domNode instanceof DOMElement) {
            throw new InvalidArgumentException(message: 'The XML node must be an element, not an attribute.');
        }

        return $domNode;
    }
}
