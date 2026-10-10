<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

/**
 * The access rights of a user or the rights a view requires. Rights are free strings of the project, yuf knows none.
 */
final class AccessRightCollection
{
    /** @var list<string> */
    private array $accessRights = [];

    private function __construct() {}

    public static function createEmpty(): AccessRightCollection
    {
        return new AccessRightCollection();
    }

    /**
     * Empty strings are no rights and are skipped (a user without rights, e.g. from an empty database column, gets an
     * empty collection).
     *
     * @param list<string> $input
     */
    public static function createFromStringArray(array $input): AccessRightCollection
    {
        $accessRightCollection = new AccessRightCollection();
        foreach ($input as $value) {
            if ($value === '') {
                continue;
            }
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
