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
 * An attribute of an `HtmlTag`, rendered as `name="value"` (or just `name` without a value). Create it with
 * `fromText()` (plain text, escaped when rendered), `fromHtml()` (encoded or trusted HTML) or `fromName()` (no value).
 */
final class HtmlTagAttribute extends HtmlElement
{
    private const string NAME_PATTERN = '/^[A-Za-z_:][A-Za-z0-9_:.-]*$/D';

    /**
     * @param string $name Letters, digits and `_ : . -`, starting with a letter, `_` or `:` (`data-id`, `aria-label`)
     * @param string|int|null $value `null` renders a boolean attribute (`disabled`)
     * @param bool $valueIsHtml `true` if the value is output as it is, `false` if it is plain text, escaped here
     *
     * @throws InvalidArgumentException for an invalid name, or an HTML value with a double quote
     */
    private function __construct(
        string $name,
        public readonly string|int|null $value,
        private readonly bool $valueIsHtml,
    ) {
        if (preg_match(pattern: HtmlTagAttribute::NAME_PATTERN, subject: $name) !== 1) {
            throw new InvalidArgumentException(
                message: 'Invalid HTML attribute name "' . $name . '": use letters, digits and _ : . - only,'
                    . ' starting with a letter, underscore or colon.',
            );
        }
        if ($valueIsHtml && is_string(value: $value) && str_contains(haystack: $value, needle: '"')) {
            throw new InvalidArgumentException(
                message: 'The value of the HTML attribute "' . $name . '" is passed as HTML but contains a double'
                    . ' quote, which would end the attribute: encode it with HtmlEncoder::encode() or use'
                    . ' HtmlTagAttribute::fromText().',
            );
        }
        parent::__construct(name: $name);
    }

    /**
     * An attribute with a plain text value (`title="a &amp; b"`), escaped when rendered.
     *
     * @param string|int $text Plain text; an integer is rendered as its decimal digits
     *
     * @throws InvalidArgumentException for an invalid name
     */
    public static function fromText(string $name, string|int $text): HtmlTagAttribute
    {
        return new HtmlTagAttribute(name: $name, value: $text, valueIsHtml: false);
    }

    /**
     * An attribute whose value is encoded already (e.g. by `HtmlEncoder::encode()`) or trusted markup. It is output as
     * it is, so a value from a user must never be passed here.
     *
     * @param string $html Encoded value; it must not contain a double quote
     *
     * @throws InvalidArgumentException for an invalid name or a double quote in the value
     */
    public static function fromHtml(string $name, string $html): HtmlTagAttribute
    {
        return new HtmlTagAttribute(name: $name, value: $html, valueIsHtml: true);
    }

    /**
     * An attribute without a value (`disabled`, `required`).
     *
     * @throws InvalidArgumentException for an invalid name
     */
    public static function fromName(string $name): HtmlTagAttribute
    {
        return new HtmlTagAttribute(name: $name, value: null, valueIsHtml: false);
    }

    #[Override]
    public function render(): string
    {
        if ($this->value === null) {
            return $this->name;
        }
        $renderValue = $this->valueIsHtml
            ? (string) $this->value
            : HtmlEncoder::encode(value: $this->value);

        return $this->name . '="' . $renderValue . '"';
    }
}
