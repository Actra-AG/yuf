<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\layout;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\html\HtmlDataObject;

readonly class NavigationItem
{
    public function __construct(
        public string $navKey,
        public string $href,
        public string $svgPath,
        public string $title,
        public AccessRightCollection $requiredAccessRights,
        public ?NavigationItemCollection $childNavigation = null,
        public string $activeSubToggleClass = 'nav-main-sub-toggle active',
        public string $inactiveSubToggleClass = 'nav-main-sub-toggle',
    ) {}

    public function render(
        string $activeMainNavigationItem,
        AccessRightCollection $accessRightCollection,
    ): HtmlDataObject {
        $childNavigationItemCollection = $this->childNavigation;
        $htmlDataObjectCollection = (
            $childNavigationItemCollection === null
            || $childNavigationItemCollection->isEmpty(accessRightCollection: $accessRightCollection)
        ) ? null : $childNavigationItemCollection->prepareForRenderer(
            activeSubNavigationItem: $activeMainNavigationItem,
            accessRightCollection: $accessRightCollection,
        );
        $htmlDataObject = new HtmlDataObject();
        $htmlDataObject->addHtml(
            propertyName: 'href',
            html: $this->href,
        );
        $htmlDataObject->addHtml(
            propertyName: 'navKey',
            html: $this->navKey,
        );
        $htmlDataObject->addHtml(
            propertyName: 'svgPath',
            html: $this->svgPath,
        );
        $htmlDataObject->addHtml(
            propertyName: 'title',
            html: $this->title,
        );
        $htmlDataObject->addHtmlDataObjectsArray(
            propertyName: 'subNavigation',
            htmlDataObjectsArray: $htmlDataObjectCollection === null ? null : $htmlDataObjectCollection->items,
        );
        if (
            $childNavigationItemCollection === null
            || $childNavigationItemCollection->isEmpty(accessRightCollection: $accessRightCollection)
        ) {
            $htmlDataObject->addHtml(
                propertyName: 'cssClass',
                html: '',
            );
        } else {
            $htmlDataObject->addHtml(
                propertyName: 'cssClass',
                html: $childNavigationItemCollection->isActive ? $this->activeSubToggleClass : $this->inactiveSubToggleClass,
            );
        }

        return $htmlDataObject;
    }

    public function hasAccess(AccessRightCollection $accessRightCollection): bool
    {
        if ((
            !$this->requiredAccessRights->isEmpty()
            && !$accessRightCollection->hasOneOfAccessRights(accessRightCollection: $this->requiredAccessRights)
        )) {
            return false;
        }
        return (
            $this->childNavigation === null
            || !$this->childNavigation->isEmpty(accessRightCollection: $accessRightCollection)
        );
    }
}
