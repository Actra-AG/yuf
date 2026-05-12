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
        public ?NavigationItemCollection $childNavigation = null
    ) {
    }

    public function render(
        string $activeMainNavigationItem,
        AccessRightCollection $accessRightCollection
    ): HtmlDataObject {
        $navigationItemCollection = $this->childNavigation;
        $htmlDataObjectCollection = (
            $navigationItemCollection === null
            || $navigationItemCollection->isEmpty(accessRightCollection: $accessRightCollection)
        ) ? null : $navigationItemCollection->prepareForRenderer(
            activeSubNavigationItem: $activeMainNavigationItem,
            accessRightCollection: $accessRightCollection
        );
        $htmlDataObject = new HtmlDataObject();
        $htmlDataObject->addTextElement(
            propertyName: 'href',
            content: $this->href,
            isEncodedForRendering: true
        );
        $htmlDataObject->addTextElement(
            propertyName: 'navKey',
            content: $this->navKey,
            isEncodedForRendering: true
        );
        $htmlDataObject->addTextElement(
            propertyName: 'svgPath',
            content: $this->svgPath,
            isEncodedForRendering: true
        );
        $htmlDataObject->addTextElement(
            propertyName: 'title',
            content: $this->title,
            isEncodedForRendering: true
        );
        $htmlDataObject->addHtmlDataObjectsArray(
            propertyName: 'subNavigation',
            htmlDataObjectsArray: is_null(value: $htmlDataObjectCollection) ? null : $htmlDataObjectCollection->items
        );
        $htmlDataObject->addTextElement(
            propertyName: 'buttonClass',
            content: (
                !is_null(value: $navigationItemCollection)
                && $navigationItemCollection->isActive
            ) ? 'nav-main-sub-toggle active' : 'nav-main-sub-toggle',
            isEncodedForRendering: true
        );

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