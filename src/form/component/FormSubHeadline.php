<?php
/**
 * @author    Christof Moser <contact@actra.ch>
 * @copyright Actra AG, Embrach, Switzerland, www.actra.ch
 * @license   MIT
 */

namespace actra\yuf\form\component;

use actra\yuf\form\FormComponent;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlText;

class FormSubHeadline extends FormComponent
{
    private int $headingLevel;
    private HtmlText $content;

    public function __construct(int $headingLevel, HtmlText $content)
    {
        $this->headingLevel = $headingLevel;
        $this->content = $content;

        parent::__construct(uniqid());
    }

    public function getHtmlTag(): HtmlTag
    {
        $headline = new HtmlTag('h' . $this->headingLevel, false, []);
        $headline->addText($this->content);

        return $headline;
    }
}