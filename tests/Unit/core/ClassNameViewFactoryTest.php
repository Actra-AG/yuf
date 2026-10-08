<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\ClassNameViewFactory;
use actra\yuf\core\ViewContext;
use actra\yuf\tests\Double\core\ViewContextFactory;
use actra\yuf\tests\Double\view\frontend\php\sample;
use actra\yuf\tests\Double\view\frontend\php\sub\nested;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * The expected class names are taken literally from the algorithm of the former Route::getPhpClassName(), which
 * read the removed RequestHandler::get().
 */
final class ClassNameViewFactoryTest extends TestCase
{
    private const string TEST_PREFIX = 'actra\yuf\tests\Double';

    private function createContext(
        string $fileTitle,
        ?string $fileGroup = null,
        string $viewGroup = 'frontend',
        string $prefix = self::TEST_PREFIX,
    ): ViewContext {
        return ViewContextFactory::create(
            fileTitle: $fileTitle,
            fileGroup: $fileGroup,
            viewGroup: $viewGroup,
            viewClassPrefix: $prefix,
        );
    }

    public function testClassNameWithoutFileGroup(): void
    {
        $context = $this->createContext(fileTitle: 'index', prefix: 'app');

        $this->assertSame(
            'app\view\frontend\php\index',
            new ClassNameViewFactory()->createClassName(context: $context),
        );
    }

    public function testClassNameWithFileGroup(): void
    {
        $context = $this->createContext(fileTitle: 'edit', fileGroup: 'user');

        $this->assertSame(
            'actra\yuf\tests\Double\view\frontend\php\user\edit',
            new ClassNameViewFactory()->createClassName(context: $context),
        );
    }

    public function testClassNameWithCustomPrefixAndEmptyViewGroup(): void
    {
        $context = $this->createContext(fileTitle: 'login', viewGroup: '', prefix: 'acme\site');

        $this->assertSame('acme\site\view\\\php\login', new ClassNameViewFactory()->createClassName(context: $context));
    }

    public function testCreateViewReturnsNullForMissingClass(): void
    {
        $context = $this->createContext(fileTitle: 'doesnotexist');

        $this->assertNull(new ClassNameViewFactory()->createView(context: $context));
    }

    public function testCreateViewThrowsForClassNotExtendingBaseView(): void
    {
        $context = $this->createContext(fileTitle: 'notaview');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs(
            'The class actra\yuf\tests\Double\view\frontend\php\notaview must extend actra\yuf\core\BaseView.',
        );
        new ClassNameViewFactory()->createView(context: $context);
    }

    public function testCreateViewReturnsInstanceOfExistingClass(): void
    {
        $context = $this->createContext(fileTitle: 'sample');

        $view = new ClassNameViewFactory()->createView(context: $context);

        $this->assertInstanceOf(sample::class, $view);
        $this->assertSame($context, $view->getContext());
    }

    public function testCreateViewWithFileGroup(): void
    {
        $context = $this->createContext(fileTitle: 'nested', fileGroup: 'sub');

        $view = new ClassNameViewFactory()->createView(context: $context);

        $this->assertInstanceOf(nested::class, $view);
        $this->assertSame($context, $view->getContext());
    }
}
