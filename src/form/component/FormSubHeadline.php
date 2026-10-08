<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component;

use actra\yuf\form\FormComponent;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlText;
use InvalidArgumentException;
use Override;

final class FormSubHeadline extends FormComponent
{
    /**
     * @param int $headingLevel 1 to 6 (`h1` to `h6`)
     *
     * @throws InvalidArgumentException for another level
     */
    public function __construct(
        private readonly int $headingLevel,
        private readonly HtmlText $content,
    ) {
        if ($headingLevel < 1 || $headingLevel > 6) {
            throw new InvalidArgumentException(
                message: 'The heading level must be between 1 and 6, ' . $headingLevel . ' given.',
            );
        }

        parent::__construct(name: bin2hex(string: random_bytes(length: 8)));
    }

    #[Override]
    public function getHtmlTag(): HtmlTag
    {
        $headline = new HtmlTag('h' . $this->headingLevel, false, []);
        $headline->addText($this->content);

        return $headline;
    }
}
