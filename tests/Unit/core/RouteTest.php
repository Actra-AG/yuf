<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\Route;
use PHPUnit\Framework\TestCase;

final class RouteTest extends TestCase
{
    public function testViewDirectoryIsExtendedByViewGroup(): void
    {
        $route = new Route(path: '/', viewDirectory: '/app/view/', viewGroup: 'frontend');

        $this->assertSame('/app/view/frontend/', $route->viewDirectory);
    }

    public function testViewDirectoryWithoutViewGroupEndsWithSlash(): void
    {
        $route = new Route(path: '/', viewDirectory: '/app/view/');

        $this->assertSame('/app/view//', $route->viewDirectory);
    }
}
