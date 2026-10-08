<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\tag;

use actra\yuf\core\LocaleHandler;
use actra\yuf\template\TemplateException;
use Closure;
use Override;

/**
 * `{tst:lang key='name' vars='selector'}`: outputs a text of the language files. The text is trusted (language files
 * are project files and may contain HTML). `vars` is the selector of an array whose values replace the placeholders
 * `[NAME]` of the text; they are escaped.
 */
final readonly class LangTag implements TemplateTag
{
    public function __construct(private LocaleHandler $localeHandler) {}

    #[Override]
    public function getName(): string
    {
        return 'lang';
    }

    #[Override]
    public function render(TemplateTagContext $context, array $attributes, ?Closure $body): string
    {
        $key = $context->requireAttribute(attributes: $attributes, name: 'key');
        if (!array_key_exists(key: $key, array: $this->localeHandler->getAllText())) {
            throw new TemplateException(reason: 'Missing language fragment "' . $key . '"');
        }
        $replacements = array_key_exists(key: 'vars', array: $attributes)
            ? $this->escapeVars(context: $context, selector: $attributes['vars'])
            : [];

        return $this->localeHandler->getText(key: $key, replacements: $replacements);
    }

    /**
     * @return array<string, string>
     */
    private function escapeVars(TemplateTagContext $context, string $selector): array
    {
        $vars = $context->resolve(selector: $selector);
        if (!is_array(value: $vars)) {
            throw new TemplateException(
                reason: 'The vars "' . $selector . '" must be an array with the values for the placeholders, got ' . get_debug_type(value: $vars),
            );
        }
        $escaped = [];
        foreach ($vars as $name => $value) {
            $escaped[(string) $name] = $context->escape(value: $value);
        }

        return $escaped;
    }
}
