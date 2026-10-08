<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

use Override;

/**
 * A text for the output: either plain text (escaped when rendered) or trusted HTML (output as it is).
 */
final class HtmlText extends HtmlElement
{
    private function __construct(
        private readonly string $content,
        private readonly bool $isHtml,
    ) {
        parent::__construct(name: 'htmlText');
    }

    /**
     * @param string $html Trusted HTML, output as it is
     */
    public static function fromHtml(string $html): HtmlText
    {
        return new HtmlText(content: $html, isHtml: true);
    }

    /**
     * @param string $text Plain text, escaped when rendered
     */
    public static function fromText(string $text): HtmlText
    {
        return new HtmlText(content: $text, isHtml: false);
    }

    #[Override]
    public function render(): string
    {
        return $this->isHtml ? $this->content : HtmlEncoder::encode(value: $this->content);
    }
}
