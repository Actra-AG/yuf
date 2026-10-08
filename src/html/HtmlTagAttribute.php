<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

use InvalidArgumentException;
use Override;

/**
 * An attribute of an `HtmlTag`, rendered as `name="value"` (or just `name` without a value).
 */
final class HtmlTagAttribute extends HtmlElement
{
    private const string NAME_PATTERN = '/^[A-Za-z_:][A-Za-z0-9_:.-]*$/D';

    /**
     * @param string $name Letters, digits and `_ : . -`, starting with a letter, `_` or `:` (`data-id`, `aria-label`)
     * @param string|int|null $value `null` renders a boolean attribute (`disabled`)
     * @param bool $valueIsEncodedForRendering `true` if the value is encoded HTML already and is output as it is (it
     *                                         must not contain a double quote); `false` for plain text, escaped here
     *
     * @throws InvalidArgumentException for an invalid name, or an encoded value with a double quote
     */
    public function __construct(
        string $name,
        public readonly string|int|null $value,
        private readonly bool $valueIsEncodedForRendering,
    ) {
        if (preg_match(pattern: HtmlTagAttribute::NAME_PATTERN, subject: $name) !== 1) {
            throw new InvalidArgumentException(
                message: 'Invalid HTML attribute name "' . $name . '": use letters, digits and _ : . - only,'
                    . ' starting with a letter, underscore or colon.',
            );
        }
        if (
            $valueIsEncodedForRendering
            && is_string(value: $value)
            && str_contains(haystack: $value, needle: '"')
        ) {
            throw new InvalidArgumentException(
                message: 'The value of the HTML attribute "' . $name . '" is marked as encoded but contains a double'
                    . ' quote, which would end the attribute: encode it with HtmlEncoder::encode() or pass'
                    . ' valueIsEncodedForRendering: false.',
            );
        }
        parent::__construct(name: $name);
    }

    #[Override]
    public function render(): string
    {
        if ($this->value === null) {
            return $this->name;
        }
        $renderValue = $this->valueIsEncodedForRendering
            ? (string) $this->value
            : HtmlEncoder::encode(value: $this->value);

        return $this->name . '="' . $renderValue . '"';
    }
}
