<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form;

use actra\yuf\form\FormNameRegistry;
use LogicException;
use PHPUnit\Framework\TestCase;

final class FormNameRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        FormNameRegistry::reset();
    }

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
        $this->expectExceptionMessage('A Form with the name "contact" has already been defined.');

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
