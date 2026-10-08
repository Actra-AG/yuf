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
        $data = self::item(navKey: 'users', title: 'Users &amp; Groups', href: '/users/?reset')->render(
            activeMainNavigationItem: 'users',
            accessRightCollection: AccessRightCollection::createEmpty(),
        )->data;

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
        $children->addItem(navigationItem: self::item(navKey: 'a', title: 'A'));
        $children->addItem(navigationItem: self::item(navKey: 'b', title: 'B'));
        $parent = self::item(navKey: 'parent', childNavigation: $children);

        $data = $parent->render(
            activeMainNavigationItem: 'b',
            accessRightCollection: AccessRightCollection::createEmpty(),
        )->data;

        $this->assertSame('nav-main-sub-toggle active', $data->cssClass);
        $this->assertIsArray($data->subNavigation);
        $this->assertSame(['a', 'b'], array_column(array: $data->subNavigation, column_key: 'navKey'));
        $this->assertSame(['A', 'B'], array_column(array: $data->subNavigation, column_key: 'title'));
    }

    public function testRenderWithChildrenWithoutActiveItem(): void
    {
        $children = new NavigationItemCollection();
        $children->addItem(navigationItem: self::item(navKey: 'a'));
        $parent = self::item(navKey: 'parent', childNavigation: $children);

        $data = $parent->render(
            activeMainNavigationItem: 'other',
            accessRightCollection: AccessRightCollection::createEmpty(),
        )->data;

        $this->assertSame('nav-main-sub-toggle', $data->cssClass);
    }

    public function testCustomToggleClasses(): void
    {
        $children = new NavigationItemCollection();
        $children->addItem(navigationItem: self::item(navKey: 'a'));
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
            )->data->cssClass,
        );
        $this->assertSame(
            'off',
            $parent->render(
                activeMainNavigationItem: 'z',
                accessRightCollection: AccessRightCollection::createEmpty(),
            )->data->cssClass,
        );
    }

    public function testChildrenWithoutAccessAreNotRendered(): void
    {
        $children = new NavigationItemCollection();
        $children->addItem(
            navigationItem: self::item(
                navKey: 'secret',
                requiredAccessRights: AccessRightCollection::createFromStringArray(input: ['admin']),
            ),
        );
        $children->addItem(navigationItem: self::item(navKey: 'open'));
        $parent = self::item(navKey: 'parent', childNavigation: $children);

        $data = $parent->render(
            activeMainNavigationItem: 'secret',
            accessRightCollection: AccessRightCollection::createEmpty(),
        )->data;

        $this->assertIsArray($data->subNavigation);
        $this->assertSame(['open'], array_column(array: $data->subNavigation, column_key: 'navKey'));
        $this->assertSame('nav-main-sub-toggle', $data->cssClass);
    }

    public function testParentWithoutAnyAccessibleChildHasNoAccess(): void
    {
        $children = new NavigationItemCollection();
        $children->addItem(
            navigationItem: self::item(
                navKey: 'secret',
                requiredAccessRights: AccessRightCollection::createFromStringArray(input: ['admin']),
            ),
        );
        $parent = self::item(navKey: 'parent', childNavigation: $children);

        $this->assertFalse($parent->hasAccess(accessRightCollection: AccessRightCollection::createEmpty()));
        $this->assertTrue(
            $parent->hasAccess(
                accessRightCollection: AccessRightCollection::createFromStringArray(input: ['admin']),
            ),
        );
    }

    public function testAccessRights(): void
    {
        $item = self::item(
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
            self::item(navKey: 'a')->hasAccess(accessRightCollection: AccessRightCollection::createEmpty()),
        );
    }

    public function testCollectionPrepareForRenderer(): void
    {
        $collection = new NavigationItemCollection();
        $collection->addItem(navigationItem: self::item(navKey: 'a'));
        $collection->addItem(
            navigationItem: self::item(
                navKey: 'b',
                requiredAccessRights: AccessRightCollection::createFromStringArray(input: ['admin']),
            ),
        );
        $collection->addItem(navigationItem: self::item(navKey: 'c'));

        $this->assertFalse($collection->isActive);
        $objects = $collection->prepareForRenderer(
            activeSubNavigationItem: 'c',
            accessRightCollection: AccessRightCollection::createEmpty(),
        );

        $this->assertCount(2, $objects->items);
        $this->assertSame('a', $objects->items[0]->data->navKey);
        $this->assertSame('c', $objects->items[1]->data->navKey);
        $this->assertTrue($collection->isActive);
    }

    public function testCollectionIsNotActiveForAnItemWithoutAccess(): void
    {
        $collection = new NavigationItemCollection();
        $collection->addItem(
            navigationItem: self::item(
                navKey: 'b',
                requiredAccessRights: AccessRightCollection::createFromStringArray(input: ['admin']),
            ),
        );
        $collection->addItem(navigationItem: self::item(navKey: 'a'));

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
            navigationItem: self::item(
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

        $secret = self::item(
            navKey: 'secret',
            requiredAccessRights: AccessRightCollection::createFromStringArray(input: ['admin']),
        );
        $open = self::item(navKey: 'open');
        $collection->addItem(navigationItem: $secret);
        $collection->addItem(navigationItem: $open);

        $this->assertSame($open, $collection->getFirst(accessRightCollection: AccessRightCollection::createEmpty()));
        $this->assertSame(
            $secret,
            $collection->getFirst(accessRightCollection: AccessRightCollection::createFromStringArray(input: ['admin'])),
        );
    }

    public function testAddingTheSameKeyReplacesTheItem(): void
    {
        $collection = new NavigationItemCollection();
        $collection->addItem(navigationItem: self::item(navKey: 'a', title: 'Old'));
        $replacement = self::item(navKey: 'a', title: 'New');
        $collection->addItem(navigationItem: $replacement);

        $this->assertSame($replacement, $collection->getFirst(accessRightCollection: AccessRightCollection::createEmpty()));
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
        $this->assertSame($href, self::item(navKey: 'a', href: $href)->href);
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
        self::item(navKey: 'a', href: $href);
    }
}
