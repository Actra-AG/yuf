<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form;

use actra\yuf\form\FormNameRegistry;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;

final class FormNameRegistryTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        FormNameRegistry::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        FormNameRegistry::reset();
    }

    public function testDifferentNamesAreRegistered(): void
    {
        FormNameRegistry::register(name: 'first');
        FormNameRegistry::register(name: 'second');

        $this->expectNotToPerformAssertions();
    }

    public function testRegisteringANameTwiceThrows(): void
    {
        FormNameRegistry::register(name: 'contact');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('A Form with the name "contact" has already been defined.');

        FormNameRegistry::register(name: 'contact');
    }

    public function testNamesAreComparedExactly(): void
    {
        FormNameRegistry::register(name: '1');
        FormNameRegistry::register(name: '01');

        $this->expectNotToPerformAssertions();
    }

    public function testResetForgetsTheNames(): void
    {
        FormNameRegistry::register(name: 'contact');

        FormNameRegistry::reset();
        FormNameRegistry::register(name: 'contact');

        $this->expectNotToPerformAssertions();
    }
}
