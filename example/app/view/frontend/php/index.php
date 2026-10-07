<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace app\view\frontend\php;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\BaseView;
use actra\yuf\core\InputParameterCollection;
use actra\yuf\html\HtmlDocument;
use Override;

// The class name equals the requested file name ("index.html" → index)
final class index extends BaseView
{
    public function __construct()
    {
        parent::__construct(
            requiredViewGroupName: 'frontend',
            ipWhitelist: [],
            authUser: null,
            requiredAccessRights: AccessRightCollection::createEmpty(),
            inputParameterCollection: new InputParameterCollection(),
        );
    }

    #[Override]
    public function execute(): void
    {
        // Rendered with templates/default.html and html/index.html; all values are HTML-escaped
        $replacements = HtmlDocument::get()->replacements;
        $replacements->addEncodedText(identifier: 'title', content: 'Hello World');
        $replacements->addEncodedText(identifier: 'greeting', content: 'Hello World!');
    }
}
