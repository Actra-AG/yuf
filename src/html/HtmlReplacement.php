<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

use stdClass;

readonly class HtmlReplacement
{
    private function __construct(
        public HtmlText|bool|HtmlDataObject|HtmlTextCollection|HtmlDataObjectCollection|int|float|null $content,
    ) {}

    public static function htmlText(?HtmlText $htmlText): HtmlReplacement
    {
        return new HtmlReplacement(content: $htmlText);
    }

    public static function html(?string $html): HtmlReplacement
    {
        return new HtmlReplacement(content: $html === null ? null : HtmlText::fromHtml(html: $html));
    }

    public static function text(?string $text): HtmlReplacement
    {
        return new HtmlReplacement(content: $text === null ? null : HtmlText::fromText(text: $text));
    }

    public static function bool(?bool $bool): HtmlReplacement
    {
        return new HtmlReplacement(content: $bool);
    }

    public static function int(?int $int): HtmlReplacement
    {
        return new HtmlReplacement(content: $int);
    }

    public static function float(?float $float): HtmlReplacement
    {
        return new HtmlReplacement(content: $float);
    }

    public static function dataObject(?HtmlDataObject $htmlDataObject): HtmlReplacement
    {
        return new HtmlReplacement(content: $htmlDataObject);
    }

    public static function textCollection(?HtmlTextCollection $collection): HtmlReplacement
    {
        return new HtmlReplacement(content: $collection);
    }

    public static function htmlDataObjectCollection(?HtmlDataObjectCollection $collection): HtmlReplacement
    {
        return new HtmlReplacement(content: $collection);
    }

    public function getDataForRenderer(): string|bool|stdClass|array|float|null
    {
        if ($this->content instanceof HtmlText) {
            return $this->content->render();
        }
        if ($this->content instanceof HtmlTextCollection) {
            $array = [];
            foreach ($this->content->items as $htmlText) {
                $array[] = $htmlText->render();
            }

            return $array;
        }
        if ($this->content instanceof HtmlDataObjectCollection) {
            $array = [];
            foreach ($this->content->items as $htmlDataObject) {
                $array[] = $htmlDataObject->data;
            }

            return $array;
        }

        if ($this->content instanceof HtmlDataObject) {
            return $this->content->data;
        }

        return $this->content;
    }
}
