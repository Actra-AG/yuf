<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\view\frontend\php;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\BaseView;
use actra\yuf\core\InputParameterCollection;
use actra\yuf\core\ViewContext;
use Override;

// The lowercase class name follows the file name, as ClassNameViewFactory builds it
final class sample extends BaseView
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

    public function getContext(): ViewContext
    {
        return $this->context;
    }

    #[Override]
    public function execute(): void {}
}
