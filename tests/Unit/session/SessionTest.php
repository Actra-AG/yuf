<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\session;

use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\session\SessionSectionEnum;
use InvalidArgumentException;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * `Session` on an `ArraySessionStorage`: typed getters (never write), writers, the reserved key of yuf, the sections of
 * yuf, `clearUserData()`, `export()` and the session ID.
 */
final class SessionTest extends TestCase
{
    private ArraySessionStorage $storage;
    private Session $session;

    #[Override]
    protected function setUp(): void
    {
        $this->storage = new ArraySessionStorage();
        $this->session = new Session(storage: $this->storage);
    }

    public function testStringIsStoredAndRead(): void
    {
        $this->session->set(key: 'name', value: 'Ann');

        $this->assertSame('Ann', $this->session->getString(key: 'name'));
        $this->assertTrue($this->session->has(key: 'name'));
    }

    public function testEveryValueTypeIsStoredAndRead(): void
    {
        $this->session->set(key: 'int', value: 5);
        $this->session->set(key: 'float', value: 1.5);
        $this->session->set(key: 'bool', value: false);
        $this->session->set(key: 'array', value: ['a' => [1, 'b', null, true, 2.5]]);

        $this->assertSame(5, $this->session->getInt(key: 'int'));
        $this->assertSame(1.5, $this->session->getFloat(key: 'float'));
        $this->assertFalse($this->session->getBool(key: 'bool'));
        $this->assertSame(['a' => [1, 'b', null, true, 2.5]], $this->session->getArray(key: 'array'));
    }

    public function testGettersReturnNullForAMissingKey(): void
    {
        $this->assertNull($this->session->getString(key: 'missing'));
        $this->assertNull($this->session->getInt(key: 'missing'));
        $this->assertNull($this->session->getFloat(key: 'missing'));
        $this->assertNull($this->session->getBool(key: 'missing'));
        $this->assertNull($this->session->getArray(key: 'missing'));
        $this->assertFalse($this->session->has(key: 'missing'));
    }

    public function testGettersReturnNullForAValueOfAnotherType(): void
    {
        $this->session->set(key: 'text', value: '5');
        $this->session->set(key: 'number', value: 5);

        $this->assertNull($this->session->getInt(key: 'text'));
        $this->assertNull($this->session->getBool(key: 'text'));
        $this->assertNull($this->session->getArray(key: 'text'));
        $this->assertNull($this->session->getString(key: 'number'));
        $this->assertNull($this->session->getFloat(key: 'number'));
    }

    public function testStoredNullIsAKeyThatHasNoValue(): void
    {
        $this->session->set(key: 'nothing', value: null);

        $this->assertTrue($this->session->has(key: 'nothing'));
        $this->assertNull($this->session->getString(key: 'nothing'));
    }

    public function testGettersNeverWrite(): void
    {
        $this->session->getString(key: 'a');
        $this->session->getInt(key: 'b');
        $this->session->getBool(key: 'c');
        $this->session->getArray(key: 'd');
        $this->session->has(key: 'e');
        $this->session->getSection(section: SessionSectionEnum::TABLES);
        $this->session->export();

        $this->assertSame([], $this->storage->all());
    }

    public function testRemoveDeletesAKey(): void
    {
        $this->session->set(key: 'name', value: 'Ann');

        $this->session->remove(key: 'name');

        $this->assertFalse($this->session->has(key: 'name'));
        $this->assertSame([], $this->storage->all());
    }

    public function testRemoveOfAMissingKeyDoesNothing(): void
    {
        $this->session->remove(key: 'missing');

        $this->assertSame([], $this->storage->all());
    }

    public function testSetReplacesAValue(): void
    {
        $this->session->set(key: 'name', value: 'Ann');
        $this->session->set(key: 'name', value: 'Bob');

        $this->assertSame('Bob', $this->session->getString(key: 'name'));
    }

    public function testObjectIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs(
            'A session value must be a string, number, boolean, null or an array of these, stdClass given.',
        );

        $this->session->set(key: 'object', value: [new stdClass()]);
    }

    public function testKeyOfYufIsReservedForWriting(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The session key "yuf" is reserved for yuf.');

        $this->session->set(key: 'yuf', value: 'mine');
    }

    public function testKeyOfYufIsReservedForRemoving(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->session->remove(key: 'yuf');
    }

    public function testSectionIsStoredBelowYuf(): void
    {
        $this->session->setSection(section: SessionSectionEnum::TABLES, data: ['items' => ['sortColumn' => 'name']]);

        $this->assertSame(
            ['items' => ['sortColumn' => 'name']],
            $this->session->getSection(section: SessionSectionEnum::TABLES),
        );
        $this->assertSame(['yuf' => ['tables' => ['items' => ['sortColumn' => 'name']]]], $this->storage->all());
    }

    public function testProjectDataAndSectionsDoNotCollide(): void
    {
        $this->session->set(key: 'tables', value: 'project data');
        $this->session->setSection(section: SessionSectionEnum::TABLES, data: ['a' => 'b']);

        $this->assertSame('project data', $this->session->getString(key: 'tables'));
        $this->assertSame(['a' => 'b'], $this->session->getSection(section: SessionSectionEnum::TABLES));
    }

    public function testSettingAnEmptySectionRemovesIt(): void
    {
        $this->session->setSection(section: SessionSectionEnum::TABLES, data: ['a' => 'b']);
        $this->session->setSection(section: SessionSectionEnum::SEARCH, data: ['c' => 'd']);

        $this->session->setSection(section: SessionSectionEnum::TABLES, data: []);

        $this->assertSame(['yuf' => ['search' => ['c' => 'd']]], $this->storage->all());
    }

    public function testRemovingTheLastSectionRemovesYuf(): void
    {
        $this->session->setSection(section: SessionSectionEnum::TABLES, data: ['a' => 'b']);

        $this->session->setSection(section: SessionSectionEnum::TABLES, data: []);

        $this->assertSame([], $this->storage->all());
    }

    public function testSectionThatIsNoArrayIsReadAsEmpty(): void
    {
        $this->storage->set(key: 'yuf', value: ['tables' => 'broken']);

        $this->assertSame([], $this->session->getSection(section: SessionSectionEnum::TABLES));
    }

    public function testClearUserDataKeepsOnlyTheHandlerSection(): void
    {
        $this->storage->set(key: 'yuf', value: [
            'handler' => ['sessionCreated' => 1_790_000_000, 'preferredLanguage' => 'de'],
            'auth' => ['isLoggedIn' => true, 'authSessionId' => 5],
            'csrf' => ['token' => 'token'],
            'tables' => ['items' => ['sortColumn' => 'name']],
            'tableFilters' => ['filters' => [], 'fields' => ['f_a' => ['f_a' => 'x']]],
            'search' => ['users' => ['status' => 'active']],
            'uploads' => ['pointer' => []],
        ]);
        $this->session->set(key: 'cart', value: ['items' => 3]);
        $this->session->set(key: 'requestedPageAfterLogin', value: '/orders.html');

        $this->session->clearUserData();

        $this->assertSame(
            ['yuf' => ['handler' => ['sessionCreated' => 1_790_000_000, 'preferredLanguage' => 'de']]],
            $this->storage->all(),
        );
    }

    public function testClearUserDataWithoutHandlerDataEmptiesTheSession(): void
    {
        $this->session->set(key: 'cart', value: 'full');

        $this->session->clearUserData();

        $this->assertSame([], $this->storage->all());
    }

    public function testExportReturnsAllData(): void
    {
        $this->session->set(key: 'cart', value: 'full');
        $this->session->setSection(section: SessionSectionEnum::CSRF, data: ['token' => 'secret']);

        $this->assertSame(
            ['cart' => 'full', 'yuf' => ['csrf' => ['token' => 'secret']]],
            $this->session->export(),
        );
    }

    public function testIdIsTheIdOfTheStorageAndChangesWithRegenerateId(): void
    {
        $this->assertSame('array-session', $this->session->getId());

        $this->session->regenerateId();

        $this->assertSame('array-session-1', $this->session->getId());
    }

    public function testRegenerateIdKeepsTheData(): void
    {
        $this->session->set(key: 'cart', value: 'full');

        $this->session->regenerateId();

        $this->assertSame('full', $this->session->getString(key: 'cart'));
    }

    public function testCloseDelegatesToTheStorageAndKeepsTheDataReadable(): void
    {
        $this->session->set(key: 'cart', value: 'full');

        $this->session->close();

        $this->assertSame('full', $this->session->getString(key: 'cart'));
        $this->assertTrue($this->session->has(key: 'cart'));
        $this->assertSame('array-session', $this->session->getId());
        $this->assertSame(['cart' => 'full'], $this->session->export());
    }

    public function testWritingAfterCloseThrows(): void
    {
        $this->session->set(key: 'cart', value: 'full');
        $this->session->close();

        foreach (
            [
                fn() => $this->session->set(key: 'cart', value: 'empty'),
                fn() => $this->session->remove(key: 'cart'),
                fn() => $this->session->regenerateId(),
                fn() => $this->session->clearUserData(),
                fn() => $this->session->setSection(section: SessionSectionEnum::CSRF, data: ['token' => 'x']),
            ] as $write
        ) {
            try {
                $write();
                SessionTest::fail('The write after the close must throw.');
            } catch (LogicException $logicException) {
                $this->assertStringContainsString('The session is closed', $logicException->getMessage());
            }
        }

        $this->assertSame(['cart' => 'full'], $this->session->export());
    }
}
