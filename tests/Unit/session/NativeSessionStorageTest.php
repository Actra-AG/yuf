<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\session;

use actra\yuf\session\NativeSessionStorage;
use actra\yuf\tests\Double\session\NonStartingSessionHandler;
use Closure;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * The storage on `$_SESSION` (the array of the test stands in for a started session; the handler double records when it
 * is started and closed).
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

    public function testNoSessionIsStartedUntilTheStorageIsUsed(): void
    {
        $this->assertSame(0, $this->sessionHandler->starts);
        $this->assertFalse($this->sessionHandler->isStarted());
    }

    public function testFirstReadStartsTheSession(): void
    {
        $this->storage->get(key: 'a');

        $this->assertSame(1, $this->sessionHandler->starts);
    }

    public function testIsActiveAsksTheHandlerAndStartsNothing(): void
    {
        $isActiveBefore = $this->storage->isActive();
        $startsBefore = $this->sessionHandler->starts;
        $this->sessionHandler->ensureStarted();

        $this->assertFalse($isActiveBefore);
        $this->assertSame(0, $startsBefore);
        $this->assertTrue($this->storage->isActive());
    }

    /**
     * @param Closure(NativeSessionStorage): void $access
     */
    #[DataProvider('accessProvider')]
    public function testEveryAccessStartsTheSessionOnce(Closure $access): void
    {
        $access($this->storage);
        $access($this->storage);

        $this->assertSame(1, $this->sessionHandler->starts);
    }

    /**
     * @return iterable<string, array{Closure(NativeSessionStorage): void}>
     */
    public static function accessProvider(): iterable
    {
        yield 'has' => [static function (NativeSessionStorage $storage): void {
            $storage->has(key: 'a');
        }];
        yield 'get' => [static function (NativeSessionStorage $storage): void {
            $storage->get(key: 'a');
        }];
        yield 'all' => [static function (NativeSessionStorage $storage): void {
            $storage->all();
        }];
        yield 'set' => [static function (NativeSessionStorage $storage): void {
            $storage->set(key: 'a', value: 1);
        }];
        yield 'remove' => [static function (NativeSessionStorage $storage): void {
            $storage->remove(key: 'a');
        }];
        yield 'replaceAll' => [static function (NativeSessionStorage $storage): void {
            $storage->replaceAll(data: []);
        }];
        yield 'getId' => [static function (NativeSessionStorage $storage): void {
            $storage->getId();
        }];
        yield 'regenerateId' => [static function (NativeSessionStorage $storage): void {
            $storage->regenerateId();
        }];
    }

    public function testClosedSessionCanStillBeRead(): void
    {
        $this->storage->set(key: 'a', value: 'b');

        $this->storage->close();

        $this->assertSame(1, $this->sessionHandler->closes);
        $this->assertSame('b', $this->storage->get(key: 'a'));
        $this->assertTrue($this->storage->has(key: 'a'));
        $this->assertSame(['a' => 'b'], $this->storage->all());
        $this->assertSame('native-test-session-0', $this->storage->getId());
    }

    /**
     * @param Closure(NativeSessionStorage): void $write
     */
    #[DataProvider('writeProvider')]
    public function testClosedSessionCannotBeWritten(Closure $write): void
    {
        $this->storage->set(key: 'a', value: 'b');
        $this->storage->close();

        try {
            $write($this->storage);
            NativeSessionStorageTest::fail('The write after the close must throw.');
        } catch (LogicException $logicException) {
            $this->assertStringContainsString('The session is closed', $logicException->getMessage());
        }

        $this->assertSame(['a' => 'b'], $_SESSION);
        $this->assertSame(0, $this->sessionHandler->regenerations);
    }

    /**
     * @return iterable<string, array{Closure(NativeSessionStorage): void}>
     */
    public static function writeProvider(): iterable
    {
        yield 'set' => [static function (NativeSessionStorage $storage): void {
            $storage->set(key: 'a', value: 'c');
        }];
        yield 'remove' => [static function (NativeSessionStorage $storage): void {
            $storage->remove(key: 'a');
        }];
        yield 'replaceAll' => [static function (NativeSessionStorage $storage): void {
            $storage->replaceAll(data: []);
        }];
        yield 'regenerateId' => [static function (NativeSessionStorage $storage): void {
            $storage->regenerateId();
        }];
    }

    public function testSessionClosedBeforeItsFirstUseCannotBeStarted(): void
    {
        $this->storage->close();

        $this->expectException(LogicException::class);

        $this->storage->get(key: 'a');
    }
}
