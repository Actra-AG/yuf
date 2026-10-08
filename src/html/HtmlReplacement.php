<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

use actra\yuf\template\runtime\TrustedHtml;
use Closure;
use stdClass;

/**
 * One value of a template: HTML text, a scalar, a data object or a list of them.
 *
 * @phpstan-type RendererValue string|int|float|bool|stdClass|TrustedHtml|list<string>|list<stdClass>|null
 */
final readonly class HtmlReplacement
{
    /**
     * @param HtmlText|bool|HtmlDataObject|HtmlTextCollection|HtmlDataObjectCollection|int|float
     *     |(Closure(): string)|null $content
     */
    private function __construct(
        public HtmlText|bool|HtmlDataObject|HtmlTextCollection|HtmlDataObjectCollection|int|float|Closure|null $content,
    ) {}

    public static function fromHtmlText(?HtmlText $htmlText): HtmlReplacement
    {
        return new HtmlReplacement(content: $htmlText);
    }

    /**
     * @param string|null $html Trusted HTML, output as it is
     */
    public static function fromHtml(?string $html): HtmlReplacement
    {
        return new HtmlReplacement(content: $html === null ? null : HtmlText::fromHtml(html: $html));
    }

    /**
     * HTML that is only built when a template reads the value (once), e.g. the CSRF field, whose token needs the
     * session.
     *
     * @param Closure(): string $html Returns trusted HTML, output as it is
     */
    public static function fromLazyHtml(Closure $html): HtmlReplacement
    {
        return new HtmlReplacement(content: $html);
    }

    /**
     * @param string|null $text Plain text, escaped when rendered
     */
    public static function fromText(?string $text): HtmlReplacement
    {
        return new HtmlReplacement(content: $text === null ? null : HtmlText::fromText(text: $text));
    }

    public static function fromBool(?bool $bool): HtmlReplacement
    {
        return new HtmlReplacement(content: $bool);
    }

    public static function fromInt(?int $int): HtmlReplacement
    {
        return new HtmlReplacement(content: $int);
    }

    public static function fromFloat(?float $float): HtmlReplacement
    {
        return new HtmlReplacement(content: $float);
    }

    public static function fromDataObject(?HtmlDataObject $htmlDataObject): HtmlReplacement
    {
        return new HtmlReplacement(content: $htmlDataObject);
    }

    public static function fromTextCollection(?HtmlTextCollection $collection): HtmlReplacement
    {
        return new HtmlReplacement(content: $collection);
    }

    public static function fromHtmlDataObjectCollection(?HtmlDataObjectCollection $collection): HtmlReplacement
    {
        return new HtmlReplacement(content: $collection);
    }

    /**
     * The value for the template engine: texts are rendered to HTML (escaped or as they are).
     *
     * @return RendererValue
     */
    public function getDataForRenderer(): string|int|float|bool|stdClass|TrustedHtml|array|null
    {
        $content = $this->content;
        if ($content instanceof Closure) {
            return new TrustedHtml(html: $content);
        }
        if ($content instanceof HtmlText) {
            return $content->render();
        }
        if ($content instanceof HtmlTextCollection) {
            return array_map(
                callback: static fn(HtmlText $htmlText): string => $htmlText->render(),
                array: $content->items,
            );
        }
        if ($content instanceof HtmlDataObjectCollection) {
            return array_map(
                callback: static fn(HtmlDataObject $htmlDataObject): stdClass => $htmlDataObject->data,
                array: $content->items,
            );
        }
        if ($content instanceof HtmlDataObject) {
            return $content->data;
        }

        return $content;
    }
}
