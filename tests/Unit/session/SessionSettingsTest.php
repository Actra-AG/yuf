<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\session;

use actra\yuf\session\SessionSettings;
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
}
