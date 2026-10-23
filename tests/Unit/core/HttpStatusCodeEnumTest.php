<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\HttpStatusCodeEnum;
use PHPUnit\Framework\TestCase;

final class HttpStatusCodeEnumTest extends TestCase
{
    public function testTooManyRequests(): void
    {
        $this->assertSame('HTTP/1.1 429 Too Many Requests', HttpStatusCodeEnum::HTTP_TOO_MANY_REQUESTS->getStatusHeader());
    }

    public function testStatusWithoutDescription(): void
    {
        $this->assertSame('HTTP/1.1 426', HttpStatusCodeEnum::HTTP_UPGRADE_REQUIRED->getStatusHeader());
    }
}
