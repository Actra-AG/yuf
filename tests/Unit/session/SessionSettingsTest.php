<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\session;

use actra\yuf\session\SessionSettings;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SessionSettingsTest extends TestCase
{
    public function testSavePathIsNullByDefault(): void
    {
        $settings = new SessionSettings();

        $this->assertNull($settings->savePath);
        $this->assertSame('', $settings->individualName);
        $this->assertSame(3600, $settings->maxLifeTime);
        $this->assertTrue($settings->isSameSiteStrict);
    }

    public function testSavePathIsKept(): void
    {
        $settings = new SessionSettings(savePath: '/var/sessions');

        $this->assertSame('/var/sessions', $settings->savePath);
    }

    public function testLifetimeBelowOneSecondIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SessionSettings(maxLifeTime: 0);
    }

    public function testNegativeGarbageCollectionProbabilityIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SessionSettings(gcProbability: -1);
    }

    public function testGarbageCollectionDivisorBelowOneIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SessionSettings(gcDivisor: 0);
    }

    public function testGarbageCollectionCanBeSwitchedOff(): void
    {
        $this->assertSame(0, new SessionSettings(gcProbability: 0)->gcProbability);
    }
}
