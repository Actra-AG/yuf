<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

/**
 * The access rights of a user or the rights a view requires. Rights are free strings of the project; the only right
 * yuf knows is `ACCESS_DO_PASSWORD_LOGIN`.
 */
final class AccessRightCollection
{
    public const string ACCESS_DO_PASSWORD_LOGIN = 'doPasswordLogin';

    /** @var list<string> */
    private array $accessRights = [];

    private function __construct() {}

    public static function createEmpty(): AccessRightCollection
    {
        return new AccessRightCollection();
    }

    /**
     * @param list<string> $input
     */
    public static function createFromStringArray(array $input): AccessRightCollection
    {
        $accessRightCollection = new AccessRightCollection();
        foreach ($input as $value) {
            $accessRightCollection->add(accessRight: $value);
        }

        return $accessRightCollection;
    }

    public function add(string $accessRight): void
    {
        $this->accessRights[] = $accessRight;
    }

    public function hasOneOfAccessRights(AccessRightCollection $accessRightCollection): bool
    {
        return array_any(
            $accessRightCollection->listAccessRights(),
            fn(string $accessRight): bool => $this->hasAccessRight(accessRight: $accessRight),
        );
    }

    /**
     * @return list<string>
     */
    public function listAccessRights(): array
    {
        return $this->accessRights;
    }

    public function hasAccessRight(string $accessRight): bool
    {
        return in_array(needle: $accessRight, haystack: $this->accessRights, strict: true);
    }

    public function isEmpty(): bool
    {
        return $this->accessRights === [];
    }
}
