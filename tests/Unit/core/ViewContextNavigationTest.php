<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\ViewContext;
use actra\yuf\layout\NavigationItem;
use actra\yuf\layout\NavigationItemCollection;
use actra\yuf\tests\Double\core\ViewContextFactory;
use ArrayObject;
use PHPUnit\Framework\TestCase;

/**
 * The navigation of a request is built by the provider of the application, once per `ViewContext`.
 */
final class ViewContextNavigationTest extends TestCase
{
    private static function navigationOf(string $navKey): NavigationItemCollection
    {
        $navigation = new NavigationItemCollection();
        $navigation->addItem(
            navigationItem: new NavigationItem(
                navKey: $navKey,
                href: '/' . $navKey . '/',
                svgPath: 'M0 0',
                title: $navKey,
                requiredAccessRights: AccessRightCollection::createEmpty(),
            ),
        );

        return $navigation;
    }

    public function testWithoutProviderThereIsNoNavigation(): void
    {
        $this->assertNull(ViewContextFactory::create()->getNavigation());
    }

    public function testProviderIsNotCalledUntilTheNavigationIsRead(): void
    {
        $calls = new ArrayObject();
        $context = ViewContextFactory::create(
            navigationProvider: static function (ViewContext $context) use ($calls): NavigationItemCollection {
                $calls->append(value: $context->fileTitle);

                return new NavigationItemCollection();
            },
        );

        $this->assertCount(0, $calls);
        $context->getNavigation();
        $this->assertCount(1, $calls);
    }

    public function testNavigationIsBuiltOncePerContext(): void
    {
        $calls = new ArrayObject();
        $context = ViewContextFactory::create(
            navigationProvider: static function (ViewContext $context) use ($calls): NavigationItemCollection {
                $calls->append(value: $context->fileTitle);

                return ViewContextNavigationTest::navigationOf(navKey: 'home');
            },
        );

        $this->assertSame($context->getNavigation(), $context->getNavigation());
        $this->assertCount(1, $calls);
    }

    public function testEachRequestBuildsItsOwnNavigationFromItsContext(): void
    {
        $provider = static fn(ViewContext $context): NavigationItemCollection
            => ViewContextNavigationTest::navigationOf(navKey: $context->fileTitle);
        $first = ViewContextFactory::create(fileTitle: 'users', navigationProvider: $provider);
        $second = ViewContextFactory::create(fileTitle: 'orders', navigationProvider: $provider);

        $this->assertTrue($first->getNavigation()?->has(navKey: 'users') ?? false);
        $this->assertFalse($first->getNavigation()?->has(navKey: 'orders') ?? true);
        $this->assertTrue($second->getNavigation()?->has(navKey: 'orders') ?? false);
        $this->assertNotSame($first->getNavigation(), $second->getNavigation());
    }
}
