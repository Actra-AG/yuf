<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\layout;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\html\HtmlDataObject;
use actra\yuf\html\HtmlSanitizer;
use actra\yuf\html\HtmlText;
use InvalidArgumentException;

/**
 * An entry of the navigation of a page, with optional children. The `title` is an `HtmlText` (`fromText()` encodes it);
 * `svgPath` and the CSS classes are trusted HTML of the application and are output as they are, so never pass user
 * data. `href` is checked.
 */
final readonly class NavigationItem
{
    /**
     * @param string $href Relative or absolute URL of the page: no control characters, no `"`, `<`, `>`, and allowed
     *                     by `HtmlSanitizer::isAllowedUrl()` (`http`, `https`, `mailto`, `tel` or no scheme)
     *
     * @throws InvalidArgumentException for an invalid `href`
     */
    public function __construct(
        public string $navKey,
        public string $href,
        public string $svgPath,
        public HtmlText $title,
        public AccessRightCollection $requiredAccessRights,
        public ?NavigationItemCollection $childNavigation = null,
        public string $activeSubToggleClass = 'nav-main-sub-toggle active',
        public string $inactiveSubToggleClass = 'nav-main-sub-toggle',
    ) {
        NavigationItem::assertValidHref(href: $href);
    }

    public function render(
        string $activeMainNavigationItem,
        AccessRightCollection $accessRightCollection,
    ): HtmlDataObject {
        $childNavigation = $this->childNavigation;
        $hasChildren = $childNavigation !== null && !$childNavigation->isEmpty(
            accessRightCollection: $accessRightCollection,
        );
        $htmlDataObject = new HtmlDataObject();
        $htmlDataObject->addHtml(propertyName: 'href', html: $this->href);
        $htmlDataObject->addHtml(propertyName: 'navKey', html: $this->navKey);
        $htmlDataObject->addHtml(propertyName: 'svgPath', html: $this->svgPath);
        $htmlDataObject->addHtmlText(propertyName: 'title', htmlText: $this->title);
        if ($childNavigation === null || !$hasChildren) {
            $htmlDataObject->addHtmlDataObjectsArray(propertyName: 'subNavigation', htmlDataObjectsArray: null);
            $htmlDataObject->addHtml(propertyName: 'cssClass', html: '');

            return $htmlDataObject;
        }
        $children = $childNavigation->prepareForRenderer(
            activeSubNavigationItem: $activeMainNavigationItem,
            accessRightCollection: $accessRightCollection,
        );
        $htmlDataObject->addHtmlDataObjectsArray(propertyName: 'subNavigation', htmlDataObjectsArray: $children->items);
        $htmlDataObject->addHtml(
            propertyName: 'cssClass',
            html: $childNavigation->isActive ? $this->activeSubToggleClass : $this->inactiveSubToggleClass,
        );

        return $htmlDataObject;
    }

    public function hasAccess(AccessRightCollection $accessRightCollection): bool
    {
        if (
            !$this->requiredAccessRights->isEmpty()
            && !$accessRightCollection->hasOneOfAccessRights(accessRightCollection: $this->requiredAccessRights)
        ) {
            return false;
        }

        return $this->childNavigation === null || !$this->childNavigation->isEmpty(
            accessRightCollection: $accessRightCollection,
        );
    }

    private static function assertValidHref(string $href): void
    {
        if (preg_match(pattern: '/[\x00-\x1F\x7F"<>]/', subject: $href) === 1) {
            throw new InvalidArgumentException(
                message: 'The navigation href must not contain control characters, double quotes or angle brackets.',
            );
        }
        if (!HtmlSanitizer::isAllowedUrl(url: $href)) {
            throw new InvalidArgumentException(
                message: 'The navigation href has a scheme other than http, https, mailto and tel: ' . $href,
            );
        }
    }
}
