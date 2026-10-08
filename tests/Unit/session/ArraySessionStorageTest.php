<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\session;

use actra\yuf\session\ArraySessionStorage;
use PHPUnit\Framework\TestCase;

final class ArraySessionStorageTest extends TestCase
{
    public function testStartsEmptyOrWithTheGivenData(): void
    {
        $this->assertSame([], new ArraySessionStorage()->all());
        $this->assertSame(['a' => 1], new ArraySessionStorage(data: ['a' => 1])->all());
    }

    public function testSetGetHasAndRemove(): void
    {
        $storage = new ArraySessionStorage();

        $storage->set(key: 'a', value: ['b' => 1]);
        $storage->set(key: 'n', value: null);

        $this->assertTrue($storage->has(key: 'a'));
        $this->assertTrue($storage->has(key: 'n'));
        $this->assertSame(['b' => 1], $storage->get(key: 'a'));
        $this->assertNull($storage->get(key: 'n'));
        $this->assertNull($storage->get(key: 'missing'));
        $this->assertFalse($storage->has(key: 'missing'));

        $storage->remove(key: 'a');

        $this->assertFalse($storage->has(key: 'a'));
    }

    public function testReplaceAllReplacesTheData(): void
    {
        $storage = new ArraySessionStorage(data: ['a' => 1]);

        $storage->replaceAll(data: ['b' => 2]);

        $this->assertSame(['b' => 2], $storage->all());
    }

    public function testIdChangesWithEveryRegeneration(): void
    {
        $storage = new ArraySessionStorage(id: 'custom');

        $this->assertSame('custom', $storage->getId());

        $storage->regenerateId();
        $this->assertSame('custom-1', $storage->getId());

        $storage->regenerateId();
        $this->assertSame('custom-2', $storage->getId());
    }
}
