<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\tag;

use actra\yuf\template\TemplateException;
use Closure;
use Override;

/**
 * `<tst:options options="selector" selected="selector"/>`: `<option>` elements for an array of value => label. A nested
 * array becomes an `<optgroup>`. `selected` is optional, a single value or an array; a value is selected when its text
 * equals the key. Keys and labels are escaped.
 */
final readonly class OptionsTag implements TemplateTag
{
    #[Override]
    public function getName(): string
    {
        return 'options';
    }

    #[Override]
    public function render(TemplateTagContext $context, array $attributes, ?Closure $body): string
    {
        $selector = $context->requireAttribute(attributes: $attributes, name: 'options');
        $options = $context->resolve(selector: $selector);
        if (!is_array(value: $options)) {
            throw new TemplateException(
                reason: 'The options "' . $selector . '" must be an array, got ' . get_debug_type(value: $options),
            );
        }
        $selection = array_key_exists(key: 'selected', array: $attributes)
            ? $this->readSelection(context: $context, selector: $attributes['selected'])
            : [];

        return $this->renderOptions(context: $context, options: $options, selection: $selection);
    }

    /**
     * @return list<string>
     */
    private function readSelection(TemplateTagContext $context, string $selector): array
    {
        $value = $context->resolve(selector: $selector);
        if ($value === null) {
            return [];
        }
        $selected = [];
        foreach (is_array(value: $value) ? $value : [$value] as $item) {
            $selected[] = $context->text(value: $item);
        }

        return $selected;
    }

    /**
     * @param array<array-key, mixed> $options
     * @param list<string> $selection
     */
    private function renderOptions(TemplateTagContext $context, array $options, array $selection): string
    {
        $html = '';
        foreach ($options as $key => $label) {
            $escapedKey = $context->escape(value: (string) $key);
            if (is_array(value: $label)) {
                $html .= '<optgroup label="' . $escapedKey . "\">\n"
                    . $this->renderOptions(context: $context, options: $label, selection: $selection) . "</optgroup>\n";

                continue;
            }
            $selected = in_array(needle: (string) $key, haystack: $selection, strict: true) ? ' selected' : '';
            $html .= '<option value="' . $escapedKey . '"' . $selected . '>'
                . $context->escape(value: $label) . "</option>\n";
        }

        return $html;
    }
}
