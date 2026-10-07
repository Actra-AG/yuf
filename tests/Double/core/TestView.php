<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\core;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\BaseView;
use actra\yuf\core\InputParameterCollection;
use actra\yuf\core\ViewContext;
use Override;

/**
 * A view without restrictions, for the view of the route's own view group.
 */
final class TestView extends BaseView
{
    public function __construct(
        ViewContext $context,
        public readonly string $name = 'test',
    ) {
        parent::__construct(
            context: $context,
            requiredViewGroupName: $context->route->viewGroup,
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
