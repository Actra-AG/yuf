<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\layout;

use actra\yuf\auth\AuthUser;
use actra\yuf\html\HtmlDataObjectCollection;

class NavigationItemCollection
{
    private(set) bool $isActive = false;
    /** @var NavigationItem[] */
    private array $items = [];

    public function __construct()
    {
    }

    public function addItem(NavigationItem $navigationItem): void
    {
        $this->items[$navigationItem->navKey] = $navigationItem;
    }

    public function prepareForRenderer(
        string $activeSubNavigationItem,
        AuthUser $authUser,
    ): HtmlDataObjectCollection {
        $htmlDataObjectCollection = new HtmlDataObjectCollection();
        foreach ($this->items as $navigationItem) {
            if (!$navigationItem->hasAccess(authUser: $authUser)) {
                continue;
            }
            $htmlDataObjectCollection->add(
                htmlDataObject: $navigationItem->render(
                    activeMainNavigationItem: $activeSubNavigationItem,
                    authUser: $authUser
                )
            );
            if ($navigationItem->navKey === $activeSubNavigationItem) {
                $this->isActive = true;
            }
        }

        return $htmlDataObjectCollection;
    }

    public function isEmpty(AuthUser $authUser): bool
    {
        $count = 0;
        foreach ($this->items as $navigationItem) {
            if ($navigationItem->hasAccess(authUser: $authUser)) {
                $count++;
            }
        }

        return ($count === 0);
    }

    public function getFirst(AuthUser $authUser): ?NavigationItem
    {
        return array_find(
            $this->items,
            fn(NavigationItem $navigationItem) => $navigationItem->hasAccess(authUser: $authUser)
        );
    }
}