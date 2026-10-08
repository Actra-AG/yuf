<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template;

use Override;

final class NewEngineTemplateOutputTagsTest extends AbstractTemplateOutputTagsTestCase
{
    #[Override]
    protected function isNewEngine(): bool
    {
        return true;
    }
}
