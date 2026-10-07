<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use Closure;
use LogicException;
use Override;

/**
 * Explicit mapping from file group and file title to a closure that creates the view. Only the view of the current
 * request is created.
 */
final class ViewMap implements ViewFactory
{
    /** @var array<string, Closure(ViewContext): BaseView> */
    private array $creators = [];

    /**
     * @param Closure(ViewContext): BaseView $create
     */
    public function add(
        string $fileTitle,
        Closure $create,
        ?string $fileGroup = null,
    ): ViewMap {
        $key = $this->createKey(
            fileGroup: $fileGroup,
            fileTitle: $fileTitle,
        );
        if (array_key_exists(key: $key, array: $this->creators)) {
            throw new LogicException(
                message: 'A view for the file title "' . $fileTitle . '"'
                    . ($fileGroup === null ? '' : ' in the file group "' . $fileGroup . '"')
                    . ' has already been added.',
            );
        }
        $this->creators[$key] = $create;

        return $this;
    }

    #[Override]
    public function createView(ViewContext $context): ?BaseView
    {
        $key = $this->createKey(
            fileGroup: $context->fileGroup,
            fileTitle: $context->fileTitle,
        );
        if (!array_key_exists(key: $key, array: $this->creators)) {
            return null;
        }

        return $this->creators[$key]($context);
    }

    private function createKey(?string $fileGroup, string $fileTitle): string
    {
        // A null group has no prefix, so it cannot collide with the group ''
        return ($fileGroup === null ? '' : 'g:' . $fileGroup) . '/' . $fileTitle;
    }
}
