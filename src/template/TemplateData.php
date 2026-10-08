<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template;

use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\template\runtime\TrustedHtml;
use LogicException;
use stdClass;

/**
 * The data of one render call. The values of a template are open by nature (`mixed`): scalars, `null`, arrays and
 * objects, which templates read with selectors. Plain strings are escaped when a template outputs them; strings that
 * the html classes already rendered to HTML (see `fromReplacements()`) are output as they are.
 *
 * @phpstan-type TemplateValue null|bool|int|float|string|object|array<array-key, mixed>
 */
final readonly class TemplateData
{
    /**
     * @param array<string, mixed> $values Plain values, escaped on output
     */
    public function __construct(public array $values = []) {}

    /**
     * Takes the replacements of the html classes. Everything they contain as text is HTML already (`HtmlText`, the
     * items of `HtmlTextCollection`, the properties of `HtmlDataObject`), so it is marked as trusted and not escaped
     * a second time.
     */
    public static function fromReplacements(HtmlReplacementCollection $replacements): TemplateData
    {
        $values = [];
        foreach ($replacements->getArrayObject() as $identifier => $value) {
            $values[$identifier] = TemplateData::markAsTrusted(value: $value);
        }

        return new TemplateData(values: $values);
    }

    public function with(string $identifier, mixed $value): TemplateData
    {
        return new TemplateData(values: [...$this->values, $identifier => $value]);
    }

    /**
     * The replacement classes only know HTML text, scalars, `stdClass` and lists of them. The value comes out of the
     * untyped data of those classes, so it is narrowed here.
     *
     * @throws LogicException for a value that is no template value (a resource)
     */
    private static function markAsTrusted(mixed $value): mixed
    {
        if (is_string(value: $value)) {
            return new TrustedHtml(html: $value);
        }
        if (is_array(value: $value)) {
            return array_map(callback: TemplateData::markAsTrusted(...), array: $value);
        }
        if ($value instanceof stdClass) {
            return TemplateData::copyAsTrusted(object: $value);
        }
        if ($value === null || is_scalar(value: $value) || is_object(value: $value)) {
            return $value;
        }

        throw new LogicException(message: 'Unsupported replacement value of type ' . get_debug_type(value: $value));
    }

    private static function copyAsTrusted(stdClass $object): stdClass
    {
        $copy = new stdClass();
        foreach (get_object_vars(object: $object) as $property => $propertyValue) {
            $copy->{$property} = TemplateData::markAsTrusted(value: $propertyValue);
        }

        return $copy;
    }
}
