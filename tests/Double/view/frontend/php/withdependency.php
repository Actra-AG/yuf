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

// A view with a dependency besides the context, created by the `create` closure of ClassNameViewFactory
final class withdependency extends BaseView
{
    public function __construct(ViewContext $context, public readonly string $projectName)
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
