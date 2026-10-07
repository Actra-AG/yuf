<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\Route;
use actra\yuf\core\ViewContext;
use actra\yuf\core\ViewMap;
use actra\yuf\tests\Double\core\TestView;
use LogicException;
use PHPUnit\Framework\TestCase;

final class ViewMapTest extends TestCase
{
    private function createContext(string $fileTitle, ?string $fileGroup = null): ViewContext
    {
        return new ViewContext(
            route: new Route(path: '/', viewDirectory: '/tmp/views/'),
            fileGroup: $fileGroup,
            fileTitle: $fileTitle,
        );
    }

    public function testCreatesViewOfKnownFileTitle(): void
    {
        $view = new TestView(name: 'index');
        $map = new ViewMap()->add(fileTitle: 'index', create: fn(ViewContext $context): TestView => $view);

        $this->assertSame($view, $map->createView(context: $this->createContext(fileTitle: 'index')));
    }

    public function testUnknownFileTitleReturnsNull(): void
    {
        $map = new ViewMap()->add(fileTitle: 'index', create: fn(ViewContext $context): TestView => new TestView());

        $this->assertNull($map->createView(context: $this->createContext(fileTitle: 'other')));
    }

    public function testEmptyMapReturnsNull(): void
    {
        $this->assertNull(new ViewMap()->createView(context: $this->createContext(fileTitle: 'index')));
    }

    public function testFileGroupIsPartOfTheKey(): void
    {
        $view = new TestView(name: 'edit');
        $map = new ViewMap()->add(
            fileTitle: 'edit',
            create: fn(ViewContext $context): TestView => $view,
            fileGroup: 'user',
        );

        $this->assertSame($view, $map->createView(context: $this->createContext(fileTitle: 'edit', fileGroup: 'user')));
        $this->assertNull($map->createView(context: $this->createContext(fileTitle: 'edit')));
        $this->assertNull($map->createView(context: $this->createContext(fileTitle: 'edit', fileGroup: 'other')));
    }

    public function testNullGroupDoesNotCollideWithEmptyGroup(): void
    {
        $withoutGroup = new TestView(name: 'without');
        $emptyGroup = new TestView(name: 'empty');
        $map = new ViewMap()
            ->add(fileTitle: 'x', create: fn(ViewContext $context): TestView => $withoutGroup)
            ->add(fileTitle: 'x', create: fn(ViewContext $context): TestView => $emptyGroup, fileGroup: '');

        $this->assertSame($withoutGroup, $map->createView(context: $this->createContext(fileTitle: 'x')));
        $this->assertSame($emptyGroup, $map->createView(context: $this->createContext(fileTitle: 'x', fileGroup: '')));
    }

    public function testGroupAndTitleAreUnambiguous(): void
    {
        $map = new ViewMap()->add(
            fileTitle: 'b',
            create: fn(ViewContext $context): TestView => new TestView(),
            fileGroup: 'a',
        );

        $this->assertNull($map->createView(context: $this->createContext(fileTitle: 'a/b')));
    }

    public function testDuplicateRegistrationThrows(): void
    {
        $map = new ViewMap()->add(fileTitle: 'index', create: fn(ViewContext $context): TestView => new TestView());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('A view for the file title "index" has already been added.');
        $map->add(fileTitle: 'index', create: fn(ViewContext $context): TestView => new TestView());
    }

    public function testDuplicateRegistrationWithFileGroupThrows(): void
    {
        $map = new ViewMap()->add(
            fileTitle: 'edit',
            create: fn(ViewContext $context): TestView => new TestView(),
            fileGroup: 'user',
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('A view for the file title "edit" in the file group "user" has already been added.');
        $map->add(fileTitle: 'edit', create: fn(ViewContext $context): TestView => new TestView(), fileGroup: 'user');
    }

    public function testClosureReceivesTheContextAndIsLazy(): void
    {
        $received = [];
        $map = new ViewMap()
            ->add(
                fileTitle: 'a',
                create: function (ViewContext $context) use (&$received): TestView {
                    $received[] = $context;

                    return new TestView();
                },
            )
            ->add(
                fileTitle: 'b',
                create: function (ViewContext $context) use (&$received): TestView {
                    $received[] = 'b called';

                    return new TestView();
                },
            );
        $context = $this->createContext(fileTitle: 'a');

        $map->createView(context: $context);

        $this->assertCount(1, $received);
        $this->assertSame($context, $received[0]);
    }
}
