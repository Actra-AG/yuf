<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use LogicException;
use Override;

/**
 * Default: the class name is built from the route and the file name, the view is created with
 * `new $className(context: $context)`.
 */
final readonly class ClassNameViewFactory implements ViewFactory
{
    public function createClassName(ViewContext $context): string
    {
        $route = $context->route;
        $classNameParts = [
            $route->viewClassPrefix,
            'view',
            $route->viewGroup,
            'php',
        ];
        if ($context->fileGroup !== null) {
            $classNameParts[] = $context->fileGroup;
        }
        $classNameParts[] = $context->fileTitle;

        return implode(
            separator: '\\',
            array: $classNameParts,
        );
    }

    #[Override]
    public function createView(ViewContext $context): ?BaseView
    {
        $className = $this->createClassName(context: $context);
        if (!class_exists(class: $className)) {
            return null;
        }
        if (!is_subclass_of(
            object_or_class: $className,
            class: BaseView::class,
        )) {
            throw new LogicException(
                message: 'The class ' . $className . ' must extend ' . BaseView::class . '.',
            );
        }

        return new $className(context: $context);
    }
}
