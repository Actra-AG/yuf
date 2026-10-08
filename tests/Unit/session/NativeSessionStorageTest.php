<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\session;

use actra\yuf\session\NativeSessionStorage;
use actra\yuf\tests\Double\session\NonStartingSessionHandler;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * The storage on `$_SESSION` (the array of the test stands in for a started session).
 */
final class NativeSessionStorageTest extends TestCase
{
    private NonStartingSessionHandler $sessionHandler;
    private NativeSessionStorage $storage;

    #[Override]
    protected function setUp(): void
    {
        $_SESSION = [];
        $this->sessionHandler = new NonStartingSessionHandler();
        $this->storage = new NativeSessionStorage(sessionHandler: $this->sessionHandler);
    }

    #[Override]
    protected function tearDown(): void
    {
        unset($_SESSION); // Sessions are disabled in the CLI
    }

    public function testValuesAreWrittenIntoAndReadFromTheSessionArray(): void
    {
        $this->storage->set(key: 'a', value: ['b' => 'c']);

        $this->assertSame(['a' => ['b' => 'c']], $_SESSION);
        $this->assertSame(['b' => 'c'], $this->storage->get(key: 'a'));
        $this->assertTrue($this->storage->has(key: 'a'));
        $this->assertSame(['a' => ['b' => 'c']], $this->storage->all());
    }

    public function testRemoveAndReplaceAll(): void
    {
        $_SESSION = ['a' => 1, 'b' => 2];

        $this->storage->remove(key: 'a');
        $this->assertSame(['b' => 2], $_SESSION);

        $this->storage->replaceAll(data: ['c' => 3]);
        $this->assertSame(['c' => 3], $_SESSION);
    }

    public function testMissingKeyIsNull(): void
    {
        $this->assertNull($this->storage->get(key: 'missing'));
        $this->assertFalse($this->storage->has(key: 'missing'));
    }

    public function testObjectsAreReadAsNullAndLeftOutOfArrays(): void
    {
        $_SESSION = ['object' => new stdClass(), 'list' => ['a', new stdClass(), 3]];

        $this->assertNull($this->storage->get(key: 'object'));
        $this->assertSame([0 => 'a', 2 => 3], $this->storage->get(key: 'list'));
        $this->assertSame(['object' => null, 'list' => [0 => 'a', 2 => 3]], $this->storage->all());
    }

    public function testIdAndRegenerationComeFromTheSessionHandler(): void
    {
        $this->assertSame('native-test-session-0', $this->storage->getId());

        $this->storage->regenerateId();

        $this->assertSame('native-test-session-1', $this->storage->getId());
        $this->assertSame(1, $this->sessionHandler->regenerations);
    }

    public function testUsingTheStorageWithoutStartedSessionThrows(): void
    {
        unset($_SESSION);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The session is not started.');

        $this->storage->get(key: 'a');
    }
}
