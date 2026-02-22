<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\htmlparser;

class DocumentNode extends HtmlNode
{
    public function __construct()
    {
        parent::__construct(nodeType: HtmlNode::DOCUMENT_NODE);
    }
}