<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\htmlparser;

class CDataSectionNode extends HtmlNode
{
    public function __construct()
    {
        parent::__construct(nodeType: HtmlNode::CDATA_SECTION_NODE);
    }
}