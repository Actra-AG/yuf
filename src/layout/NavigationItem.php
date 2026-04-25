<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\layout;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\auth\AuthUser;
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
        AuthUser $authUser
    ): HtmlDataObject {
        $navigationItemCollection = $this->childNavigation;
        $htmlDataObjectCollection = (
            is_null(value: $navigationItemCollection)
            || $navigationItemCollection->isEmpty(authUser: $authUser)
        ) ? null : $navigationItemCollection->prepareForRenderer(
            activeSubNavigationItem: $activeMainNavigationItem,
            authUser: $authUser
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

    public function hasAccess(AuthUser $authUser): bool
    {
        return (
            $this->requiredAccessRights->isEmpty()
            || $authUser->hasOneOfRights(accessRightCollection: $this->requiredAccessRights)
        );
    }
}