<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\AccessRightCollection;
use PHPUnit\Framework\TestCase;

final class AccessRightCollectionTest extends TestCase
{
    public function testEmptyCollectionHasNoRights(): void
    {
        $collection = AccessRightCollection::createEmpty();

        $this->assertTrue($collection->isEmpty());
        $this->assertSame([], $collection->listAccessRights());
        $this->assertFalse($collection->hasAccessRight(accessRight: 'read'));
    }

    public function testCollectionFromStringsKeepsTheOrderAndDuplicates(): void
    {
        $collection = AccessRightCollection::createFromStringArray(input: ['read', 'write', 'read']);

        $this->assertFalse($collection->isEmpty());
        $this->assertSame(['read', 'write', 'read'], $collection->listAccessRights());
    }

    public function testRightsCanBeAdded(): void
    {
        $collection = AccessRightCollection::createEmpty();

        $collection->add(accessRight: 'read');

        $this->assertTrue($collection->hasAccessRight(accessRight: 'read'));
        $this->assertFalse($collection->hasAccessRight(accessRight: 'Read'));
        $this->assertFalse($collection->hasAccessRight(accessRight: 'write'));
    }

    public function testRightsAreComparedStrictly(): void
    {
        $collection = AccessRightCollection::createFromStringArray(input: ['0']);

        $this->assertTrue($collection->hasAccessRight(accessRight: '0'));
        $this->assertFalse($collection->hasAccessRight(accessRight: ''));
    }

    public function testHasOneOfAccessRightsNeedsOneCommonRight(): void
    {
        $collection = AccessRightCollection::createFromStringArray(input: ['read', 'write']);

        $this->assertTrue(
            $collection->hasOneOfAccessRights(
                accessRightCollection: AccessRightCollection::createFromStringArray(input: ['admin', 'write']),
            ),
        );
        $this->assertFalse(
            $collection->hasOneOfAccessRights(
                accessRightCollection: AccessRightCollection::createFromStringArray(input: ['admin']),
            ),
        );
    }

    public function testNothingIsRequiredByAnEmptyCollection(): void
    {
        $collection = AccessRightCollection::createFromStringArray(input: ['read']);

        $this->assertFalse(
            $collection->hasOneOfAccessRights(accessRightCollection: AccessRightCollection::createEmpty()),
        );
    }
}
