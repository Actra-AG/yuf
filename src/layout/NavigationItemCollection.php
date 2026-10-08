<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\layout;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\html\HtmlDataObjectCollection;
use InvalidArgumentException;

/**
 * The navigation items of one level by `navKey`.
 */
final class NavigationItemCollection
{
    /** Whether the last `prepareForRenderer()` call found the active item among the accessible items. */
    public private(set) bool $isActive = false;
    /** @var array<string, NavigationItem> */
    private array $items = [];

    /**
     * @throws InvalidArgumentException if an item with the same `navKey` is already in the collection
     */
    public function addItem(NavigationItem $navigationItem): void
    {
        if (array_key_exists(key: $navigationItem->navKey, array: $this->items)) {
            throw new InvalidArgumentException(
                message: 'The navigation already has an item with the key ' . $navigationItem->navKey . '.',
            );
        }
        $this->items[$navigationItem->navKey] = $navigationItem;
    }

    /**
     * The data of the items the user may see, and remembers in `$isActive` whether one of them is the active item.
     */
    public function prepareForRenderer(
        string $activeSubNavigationItem,
        AccessRightCollection $accessRightCollection,
    ): HtmlDataObjectCollection {
        $this->isActive = false;
        $htmlDataObjectCollection = new HtmlDataObjectCollection();
        foreach ($this->items as $navigationItem) {
            if (!$navigationItem->hasAccess(accessRightCollection: $accessRightCollection)) {
                continue;
            }
            $htmlDataObjectCollection->add(
                htmlDataObject: $navigationItem->render(
                    activeMainNavigationItem: $activeSubNavigationItem,
                    accessRightCollection: $accessRightCollection,
                ),
            );
            if ($navigationItem->navKey === $activeSubNavigationItem) {
                $this->isActive = true;
            }
        }

        return $htmlDataObjectCollection;
    }

    public function isEmpty(AccessRightCollection $accessRightCollection): bool
    {
        return !array_any(
            $this->items,
            static fn(NavigationItem $navigationItem): bool => $navigationItem->hasAccess(
                accessRightCollection: $accessRightCollection,
            ),
        );
    }

    public function getFirst(AccessRightCollection $accessRightCollection): ?NavigationItem
    {
        return array_find(
            $this->items,
            static fn(NavigationItem $navigationItem): bool => $navigationItem->hasAccess(
                accessRightCollection: $accessRightCollection,
            ),
        );
    }
}
