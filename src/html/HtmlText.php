<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

use Override;

class HtmlText extends HtmlElement
{
    private string $content;
    private bool $isHtml;

    private function __construct(string $content, bool $isHtml)
    {
        $this->content = $content;
        $this->isHtml = $isHtml;
        parent::__construct('htmlText');
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

    /**
     * Generate the "html-code" for this Text-Element to be used for output
     *
     * @return string Generated html-code
     */
    #[Override]
    public function render(): string
    {
        return $this->isHtml ? $this->content : HtmlEncoder::encode(value: $this->content);
    }
}
