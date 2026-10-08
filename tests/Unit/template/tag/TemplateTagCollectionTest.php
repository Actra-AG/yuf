<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template\tag;

use actra\yuf\clock\FixedClock;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LocaleHandler;
use actra\yuf\template\tag\TemplateTagCollection;
use actra\yuf\template\tag\TextTag;
use actra\yuf\tests\Double\template\NamedTag;
use actra\yuf\tests\Double\template\ShoutTag;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TemplateTagCollectionTest extends TestCase
{
    private function createDefault(): TemplateTagCollection
    {
        return TemplateTagCollection::createDefault(
            localeHandler: new LocaleHandler(language: null, availableLanguages: new LanguageCollection()),
            snippetsDirectory: '/snippets/',
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '2026-01-02 03:04:05')),
        );
    }

    public function testDefaultCollectionHasTheBuiltInTags(): void
    {
        $collection = $this->createDefault();

        foreach (['text', 'loadSubTpl', 'lang', 'snippet', 'print', 'date', 'options'] as $name) {
            $tag = $collection->find(name: $name);
            $this->assertNotNull($tag, $name);
            $this->assertSame($name, $tag->getName());
        }
    }

    public function testNativeTagsAreNotInTheCollection(): void
    {
        $collection = $this->createDefault();

        foreach (['if', 'else', 'for'] as $name) {
            $this->assertNull($collection->find(name: $name));
        }
    }

    public function testUnknownNameIsNotFound(): void
    {
        $this->assertNull($this->createDefault()->find(name: 'shout'));
        $this->assertNull(new TemplateTagCollection()->find(name: 'text'));
    }

    public function testOwnTagCanBeAdded(): void
    {
        $collection = $this->createDefault()->with(tag: new ShoutTag());

        $this->assertInstanceOf(ShoutTag::class, $collection->find(name: 'shout'));
        $this->assertInstanceOf(TextTag::class, $collection->find(name: 'text'));
    }

    public function testCreateDefaultAddsOwnTags(): void
    {
        $collection = TemplateTagCollection::createDefault(
            localeHandler: new LocaleHandler(language: null, availableLanguages: new LanguageCollection()),
            snippetsDirectory: '/snippets/',
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '2026-01-02 03:04:05')),
            ownTags: [new ShoutTag(), new NamedTag(name: 'other')],
        );

        $this->assertInstanceOf(ShoutTag::class, $collection->find(name: 'shout'));
        $this->assertNotNull($collection->find(name: 'other'));
        $this->assertInstanceOf(TextTag::class, $collection->find(name: 'text'));
    }

    public function testCreateDefaultRejectsOwnTagWithBuiltInName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The template tag "date" is already registered (a built-in tag or another own tag has this name); choose another name');

        TemplateTagCollection::createDefault(
            localeHandler: new LocaleHandler(language: null, availableLanguages: new LanguageCollection()),
            snippetsDirectory: '/snippets/',
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '2026-01-02 03:04:05')),
            ownTags: [new NamedTag(name: 'date')],
        );
    }

    public function testCreateDefaultRejectsDuplicateOwnTags(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The template tag "shout" is already registered (a built-in tag or another own tag has this name); choose another name');

        TemplateTagCollection::createDefault(
            localeHandler: new LocaleHandler(language: null, availableLanguages: new LanguageCollection()),
            snippetsDirectory: '/snippets/',
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '2026-01-02 03:04:05')),
            ownTags: [new ShoutTag(), new ShoutTag()],
        );
    }

    public function testWithReturnsACopy(): void
    {
        $collection = new TemplateTagCollection(new TextTag());

        $copy = $collection->with(tag: new ShoutTag());

        $this->assertNull($collection->find(name: 'shout'));
        $this->assertNotNull($copy->find(name: 'shout'));
    }

    public function testRegisteringAnExistingNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The template tag "text" is already registered (a built-in tag or another own tag has this name); choose another name');

        $this->createDefault()->with(tag: new TextTag());
    }

    public function testTheSameNameTwiceInTheConstructorThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The template tag "shout" is already registered (a built-in tag or another own tag has this name); choose another name');

        new TemplateTagCollection(new ShoutTag(), new ShoutTag());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nativeNameProvider(): iterable
    {
        yield 'if' => ['if'];
        yield 'else' => ['else'];
        yield 'for' => ['for'];
    }

    #[DataProvider('nativeNameProvider')]
    public function testNativeTagNamesAreReserved(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The tag name "' . $name . '" is reserved for the template engine (`if`, `else` and `for` are compiled by the engine); choose another name');

        new TemplateTagCollection(new NamedTag(name: $name));
    }
}
