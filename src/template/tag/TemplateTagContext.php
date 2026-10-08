<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\tag;

use actra\yuf\template\runtime\TemplateRuntime;
use actra\yuf\template\TemplateData;
use actra\yuf\template\TemplateException;

/**
 * What a tag can do while it renders: read the template data, escape values, render a template with the same data.
 * The values of the template data are open (text, numbers, `null`, arrays, objects); use `escape()` or `text()` to
 * output them.
 *
 * @phpstan-import-type TemplateValue from TemplateData
 */
final readonly class TemplateTagContext
{
    /**
     * @internal Created by the engine
     */
    public function __construct(private TemplateRuntime $runtime) {}

    /**
     * Resolves a selector like `a.b.c` (design section 2).
     *
     * @return TemplateValue
     *
     * @throws TemplateException if the value does not exist
     */
    public function resolve(string $selector): bool|int|float|string|object|array|null
    {
        return $this->runtime->resolve(selector: $selector);
    }

    /**
     * The value as HTML: text, numbers and `Stringable` are escaped, HTML that the html classes rendered is output as
     * it is, `null` is an empty string and a boolean is `'1'` or `''`.
     *
     *
     * @throws TemplateException for arrays and other objects
     */
    public function escape(mixed $value): string
    {
        return $this->runtime->escape(value: $value);
    }

    /**
     * The value as text, not escaped (for paths, comparisons and other values that are not output).
     *
     *
     * @throws TemplateException for arrays and other objects
     */
    public function text(mixed $value): string
    {
        return $this->runtime->text(value: $value);
    }

    /**
     * Renders another template with the same data and returns its HTML.
     *
     * @throws TemplateException
     */
    public function renderTemplate(string $templateFile): string
    {
        return $this->runtime->renderTemplate(templateFile: $templateFile);
    }

    /**
     * @param array<string, string> $attributes
     *
     * @throws TemplateException if the attribute is missing
     */
    public function requireAttribute(array $attributes, string $name): string
    {
        if (!array_key_exists(key: $name, array: $attributes)) {
            throw new TemplateException(reason: 'Missing attribute "' . $name . '"');
        }

        return $attributes[$name];
    }
}
