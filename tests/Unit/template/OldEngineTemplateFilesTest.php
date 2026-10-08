<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template;

use Override;

final class OldEngineTemplateFilesTest extends AbstractTemplateFilesTestCase
{
    #[Override]
    protected function isNewEngine(): bool
    {
        return false;
    }
}
