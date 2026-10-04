<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\settings;

use actra\yuf\form\settings\AutoCompleteValue;
use actra\yuf\form\settings\PasswordPurposeEnum;
use PHPUnit\Framework\TestCase;

final class PasswordPurposeEnumTest extends TestCase
{
    public function testCurrentPurposeMapsToCurrentPassword(): void
    {
        $this->assertSame(AutoCompleteValue::CURRENT_PASSWORD, PasswordPurposeEnum::CURRENT->autoComplete());
    }

    public function testNewPurposeMapsToNewPassword(): void
    {
        $this->assertSame(AutoCompleteValue::NEW_PASSWORD, PasswordPurposeEnum::NEW->autoComplete());
    }
}