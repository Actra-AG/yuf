<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\layout;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\layout\NavigationItem;
use actra\yuf\layout\NavigationItemCollection;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NavigationItemTest extends TestCase
{
    private static function item(
        string $navKey,
        string $title = 'Title',
        ?AccessRightCollection $requiredAccessRights = null,
        ?NavigationItemCollection $childNavigation = null,
        string $href = '/x/',
    ): NavigationItem {
        return new NavigationItem(
            navKey: $navKey,
            href: $href,
            svgPath: 'M0 0',
            title: $title,
            requiredAccessRights: $requiredAccessRights ?? AccessRightCollection::createEmpty(),
            childNavigation: $childNavigation,
        );
    }

    public function testRenderWithoutChildren(): void
    {
        $data = NavigationItemTest::item(navKey: 'users', title: 'Users &amp; Groups', href: '/users/?reset')->render(
            activeMainNavigationItem: 'users',
            accessRightCollection: AccessRightCollection::createEmpty(),
        )->toTemplateData();

        $this->assertSame(
            [
                'href' => '/users/?reset',
                'navKey' => 'users',
                'svgPath' => 'M0 0',
                'title' => 'Users &amp; Groups',
                'subNavigation' => null,
                'cssClass' => '',
            ],
            get_object_vars(object: $data),
        );
    }

    public function testRenderWithChildren(): void
    {
        $children = new NavigationItemCollection();
        $children->addItem(navigationItem: NavigationItemTest::item(navKey: 'a', title: 'A'));
        $children->addItem(navigationItem: NavigationItemTest::item(navKey: 'b', title: 'B'));
        $parent = NavigationItemTest::item(navKey: 'parent', childNavigation: $children);

        $data = $parent->render(
            activeMainNavigationItem: 'b',
            accessRightCollection: AccessRightCollection::createEmpty(),
        )->toTemplateData();

        $this->assertSame('nav-main-sub-toggle active', $data->cssClass);
        $this->assertIsArray($data->subNavigation);
        $this->assertSame(['a', 'b'], array_column(array: $data->subNavigation, column_key: 'navKey'));
        $this->assertSame(['A', 'B'], array_column(array: $data->subNavigation, column_key: 'title'));
    }

    public function testRenderWithChildrenWithoutActiveItem(): void
    {
        $children = new NavigationItemCollection();
        $children->addItem(navigationItem: NavigationItemTest::item(navKey: 'a'));
        $parent = NavigationItemTest::item(navKey: 'parent', childNavigation: $children);

        $data = $parent->render(
            activeMainNavigationItem: 'other',
            accessRightCollection: AccessRightCollection::createEmpty(),
        )->toTemplateData();

        $this->assertSame('nav-main-sub-toggle', $data->cssClass);
    }

    public function testCustomToggleClasses(): void
    {
        $children = new NavigationItemCollection();
        $children->addItem(navigationItem: NavigationItemTest::item(navKey: 'a'));
        $parent = new NavigationItem(
            navKey: 'parent',
            href: '/p/',
            svgPath: '',
            title: 'P',
            requiredAccessRights: AccessRightCollection::createEmpty(),
            childNavigation: $children,
            activeSubToggleClass: 'on',
            inactiveSubToggleClass: 'off',
        );

        $this->assertSame(
            'on',
            $parent->render(
                activeMainNavigationItem: 'a',
                accessRightCollection: AccessRightCollection::createEmpty(),
            )->toTemplateData()->cssClass,
        );
        $this->assertSame(
            'off',
            $parent->render(
                activeMainNavigationItem: 'z',
                accessRightCollection: AccessRightCollection::createEmpty(),
            )->toTemplateData()->cssClass,
        );
    }

    public function testChildrenWithoutAccessAreNotRendered(): void
    {
        $children = new NavigationItemCollection();
        $children->addItem(
            navigationItem: NavigationItemTest::item(
                navKey: 'secret',
                requiredAccessRights: AccessRightCollection::createFromStringArray(input: ['admin']),
            ),
        );
        $children->addItem(navigationItem: NavigationItemTest::item(navKey: 'open'));
        $parent = NavigationItemTest::item(navKey: 'parent', childNavigation: $children);

        $data = $parent->render(
            activeMainNavigationItem: 'secret',
            accessRightCollection: AccessRightCollection::createEmpty(),
        )->toTemplateData();

        $this->assertIsArray($data->subNavigation);
        $this->assertSame(['open'], array_column(array: $data->subNavigation, column_key: 'navKey'));
        $this->assertSame('nav-main-sub-toggle', $data->cssClass);
    }

    public function testParentWithoutAnyAccessibleChildHasNoAccess(): void
    {
        $children = new NavigationItemCollection();
        $children->addItem(
            navigationItem: NavigationItemTest::item(
                navKey: 'secret',
                requiredAccessRights: AccessRightCollection::createFromStringArray(input: ['admin']),
            ),
        );
        $parent = NavigationItemTest::item(navKey: 'parent', childNavigation: $children);

        $this->assertFalse($parent->hasAccess(accessRightCollection: AccessRightCollection::createEmpty()));
        $this->assertTrue(
            $parent->hasAccess(
                accessRightCollection: AccessRightCollection::createFromStringArray(input: ['admin']),
            ),
        );
    }

    public function testAccessRights(): void
    {
        $item = NavigationItemTest::item(
            navKey: 'a',
            requiredAccessRights: AccessRightCollection::createFromStringArray(input: ['edit', 'admin']),
        );

        $this->assertFalse($item->hasAccess(accessRightCollection: AccessRightCollection::createEmpty()));
        $this->assertFalse(
            $item->hasAccess(accessRightCollection: AccessRightCollection::createFromStringArray(input: ['view'])),
        );
        $this->assertTrue(
            $item->hasAccess(accessRightCollection: AccessRightCollection::createFromStringArray(input: ['admin'])),
        );
    }

    public function testItemWithoutRequiredRightsIsOpenForEveryone(): void
    {
        $this->assertTrue(
            NavigationItemTest::item(navKey: 'a')->hasAccess(accessRightCollection: AccessRightCollection::createEmpty()),
        );
    }

    public function testCollectionPrepareForRenderer(): void
    {
        $collection = new NavigationItemCollection();
        $collection->addItem(navigationItem: NavigationItemTest::item(navKey: 'a'));
        $collection->addItem(
            navigationItem: NavigationItemTest::item(
                navKey: 'b',
                requiredAccessRights: AccessRightCollection::createFromStringArray(input: ['admin']),
            ),
        );
        $collection->addItem(navigationItem: NavigationItemTest::item(navKey: 'c'));

        $this->assertFalse($collection->isActive);
        $objects = $collection->prepareForRenderer(
            activeSubNavigationItem: 'c',
            accessRightCollection: AccessRightCollection::createEmpty(),
        );

        $this->assertCount(2, $objects->items);
        $this->assertSame('a', $objects->items[0]->toTemplateData()->navKey);
        $this->assertSame('c', $objects->items[1]->toTemplateData()->navKey);
        $this->assertTrue($collection->isActive);
    }

    public function testCollectionIsNotActiveForAnItemWithoutAccess(): void
    {
        $collection = new NavigationItemCollection();
        $collection->addItem(
            navigationItem: NavigationItemTest::item(
                navKey: 'b',
                requiredAccessRights: AccessRightCollection::createFromStringArray(input: ['admin']),
            ),
        );
        $collection->addItem(navigationItem: NavigationItemTest::item(navKey: 'a'));

        $collection->prepareForRenderer(
            activeSubNavigationItem: 'b',
            accessRightCollection: AccessRightCollection::createEmpty(),
        );

        $this->assertFalse($collection->isActive);
    }

    public function testCollectionIsEmpty(): void
    {
        $collection = new NavigationItemCollection();
        $noRights = AccessRightCollection::createEmpty();
        $this->assertTrue($collection->isEmpty(accessRightCollection: $noRights));

        $collection->addItem(
            navigationItem: NavigationItemTest::item(
                navKey: 'b',
                requiredAccessRights: AccessRightCollection::createFromStringArray(input: ['admin']),
            ),
        );
        $this->assertTrue($collection->isEmpty(accessRightCollection: $noRights));
        $this->assertFalse(
            $collection->isEmpty(
                accessRightCollection: AccessRightCollection::createFromStringArray(input: ['admin']),
            ),
        );
    }

    public function testCollectionGetFirstAccessibleItem(): void
    {
        $collection = new NavigationItemCollection();
        $this->assertNull($collection->getFirst(accessRightCollection: AccessRightCollection::createEmpty()));

        $secret = NavigationItemTest::item(
            navKey: 'secret',
            requiredAccessRights: AccessRightCollection::createFromStringArray(input: ['admin']),
        );
        $open = NavigationItemTest::item(navKey: 'open');
        $collection->addItem(navigationItem: $secret);
        $collection->addItem(navigationItem: $open);

        $this->assertSame($open, $collection->getFirst(accessRightCollection: AccessRightCollection::createEmpty()));
        $this->assertSame(
            $secret,
            $collection->getFirst(
                accessRightCollection: AccessRightCollection::createFromStringArray(input: ['admin']),
            ),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validHrefProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'absolute path' => ['/users/?reset'];
        yield 'relative path' => ['users.html'];
        yield 'query with ampersand' => ['/a?b=1&c=2'];
        yield 'fragment' => ['#top'];
        yield 'https' => ['https://example.com/a'];
        yield 'http upper case' => ['HTTP://example.com/a'];
        yield 'mailto' => ['mailto:info@example.com'];
        yield 'tel' => ['tel:+41000000000'];
        yield 'scheme relative' => ['//example.com/a'];
        yield 'colon after the path start' => ['/a:b'];
    }

    #[DataProvider('validHrefProvider')]
    public function testValidHref(string $href): void
    {
        $this->assertSame($href, NavigationItemTest::item(navKey: 'a', href: $href)->href);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidHrefProvider(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'javascript upper case' => ['JavaScript:alert(1)'];
        yield 'javascript with leading space' => [' javascript:alert(1)'];
        yield 'javascript with tab' => ["java\tscript:alert(1)"];
        yield 'javascript with line break' => ["java\nscript:alert(1)"];
        yield 'data' => ['data:text/html,<b>'];
        yield 'vbscript' => ['vbscript:x'];
        yield 'file' => ['file:///etc/passwd'];
        yield 'double quote' => ['/a" onclick="x'];
        yield 'angle bracket' => ['/a<b'];
        yield 'null byte' => ["/a\0b"];
    }

    #[DataProvider('invalidHrefProvider')]
    public function testInvalidHrefIsRejected(string $href): void
    {
        $this->expectException(InvalidArgumentException::class);
        NavigationItemTest::item(navKey: 'a', href: $href);
    }

    public function testAddItemRejectsASecondItemWithTheSameKey(): void
    {
        $collection = new NavigationItemCollection();
        $collection->addItem(navigationItem: NavigationItemTest::item(navKey: 'a', title: 'First'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The navigation already has an item with the key a.');
        $collection->addItem(navigationItem: NavigationItemTest::item(navKey: 'a', title: 'Second'));
    }

    public function testRejectedItemDoesNotReplaceTheEarlierOne(): void
    {
        $collection = new NavigationItemCollection();
        $collection->addItem(navigationItem: NavigationItemTest::item(navKey: 'a', title: 'First'));

        try {
            $collection->addItem(navigationItem: NavigationItemTest::item(navKey: 'a', title: 'Second'));
        } catch (InvalidArgumentException) {
        }

        $data = $collection->prepareForRenderer(
            activeSubNavigationItem: '',
            accessRightCollection: AccessRightCollection::createEmpty(),
        );
        $this->assertCount(1, $data->items);
        $this->assertSame('First', $data->items[0]->toTemplateData()->title);
    }

    public function testCollectionKnowsItsKeysOnItsLevel(): void
    {
        $child = new NavigationItemCollection();
        $child->addItem(navigationItem: NavigationItemTest::item(navKey: 'child'));
        $collection = new NavigationItemCollection();
        $collection->addItem(
            navigationItem: NavigationItemTest::item(
                navKey: 'main',
                requiredAccessRights: AccessRightCollection::createFromStringArray(input: ['admin']),
                childNavigation: $child,
            ),
        );

        $this->assertTrue($collection->has(navKey: 'main'), 'also without the access right of the item');
        $this->assertFalse($collection->has(navKey: 'child'), 'children are not searched');
        $this->assertFalse($collection->has(navKey: 'Main'));
        $this->assertFalse($collection->has(navKey: ''));
        $this->assertFalse(new NavigationItemCollection()->has(navKey: 'main'));
    }
}
