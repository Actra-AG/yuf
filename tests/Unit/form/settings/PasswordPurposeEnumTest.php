<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\settings;

use actra\yuf\form\settings\AutoCompleteEnum;
use actra\yuf\form\settings\PasswordPurposeEnum;
use PHPUnit\Framework\TestCase;

final class PasswordPurposeEnumTest extends TestCase
{
    public function testCurrentPurposeMapsToCurrentPassword(): void
    {
        $this->assertSame(AutoCompleteEnum::CURRENT_PASSWORD, PasswordPurposeEnum::CURRENT->autoComplete());
    }

    public function testNewPurposeMapsToNewPassword(): void
    {
        $this->assertSame(AutoCompleteEnum::NEW_PASSWORD, PasswordPurposeEnum::NEW->autoComplete());
    }
}
