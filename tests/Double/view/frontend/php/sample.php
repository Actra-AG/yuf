<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\view\frontend\php;

use actra\yuf\core\BaseView;
use Override;

// The lowercase class name follows the file name, as ClassNameViewFactory builds it
final class sample extends BaseView
{
    // BaseView::__construct() needs RequestHandler::get(), which cannot be built in tests
    // @phpstan-ignore constructor.missingParentCall
    public function __construct() {}

    #[Override]
    public function execute(): void {}
}
