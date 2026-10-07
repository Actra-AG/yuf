<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

interface ViewFactory
{
    /**
     * Creates the view for a request of a route.
     *
     * @return BaseView|null `null` if the route has no view for this file: the content file is rendered without
     *     view, as before
     */
    public function createView(ViewContext $context): ?BaseView;
}
