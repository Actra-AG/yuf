<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace app\view\frontend;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\BaseView;
use actra\yuf\core\InputParameterCollection;
use actra\yuf\core\ViewContext;
use Override;

// Registered for "index.html" in the ViewMap of the route (see example/public/index.php)
final class IndexView extends BaseView
{
    public function __construct(ViewContext $context)
    {
        parent::__construct(
            context: $context,
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
        $replacements = $this->getHtmlDocument()->replacements;
        $replacements->addEncodedText(identifier: 'title', content: 'Hello World');
        $replacements->addEncodedText(identifier: 'greeting', content: 'Hello World!');
    }
}
