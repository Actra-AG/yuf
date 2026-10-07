<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\core;

use actra\yuf\core\BaseView;
use Override;

/**
 * A view that does not call the BaseView constructor, which reads RequestHandler::get() (cannot be built in tests).
 */
final class TestView extends BaseView
{
    // BaseView::__construct() needs RequestHandler::get(), which cannot be built in tests
    // @phpstan-ignore constructor.missingParentCall
    public function __construct(public readonly string $name = 'test') {}

    #[Override]
    public function execute(): void {}
}
