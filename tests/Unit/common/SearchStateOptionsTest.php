<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\common;

use actra\yuf\common\SearchState;
use actra\yuf\core\InputSourceEnum;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\session\SessionSectionEnum;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use Override;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * The search state methods that take `FormOptions`: keys stay strings (or become integers for integer options), the
 * remembered value is a string or a list of strings.
 */
final class SearchStateOptionsTest extends TestCase
{
    private Session $session;

    #[Override]
    protected function setUp(): void
    {
        $this->session = new Session(storage: new ArraySessionStorage());
    }

    /**
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $post
     */
    private function helper(array $query = [], array $post = []): SearchState
    {
        return SearchState::create(
            instanceName: 'users',
            httpRequest: HttpRequestFactory::create(queryParameters: $query, postParameters: $post),
            valueSource: InputSourceEnum::POST,
            session: $this->session,
        );
    }

    private function intOptions(): FormOptions
    {
        $formOptions = new FormOptions();
        $formOptions->addIntItem(key: 1, htmlText: HtmlText::fromHtml(html: 'One'));
        $formOptions->addIntItem(key: 2, htmlText: HtmlText::fromHtml(html: 'Two'));
        $formOptions->addIntItem(key: 3, htmlText: HtmlText::fromHtml(html: 'Three'));

        return $formOptions;
    }

    private function textOptions(): FormOptions
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: 'a', htmlText: HtmlText::fromHtml(html: 'A'));
        $formOptions->addItem(key: 'b', htmlText: HtmlText::fromHtml(html: 'B'));

        return $formOptions;
    }

    /**
     * @return string|list<string>|null What the search state remembers for the field of the instance `users`
     */
    private function storedField(string $field): string|array|null
    {
        $section = $this->session->getSection(section: SessionSectionEnum::SEARCH);
        $instance = array_key_exists(key: 'users', array: $section) ? $section['users'] : [];
        if (!is_array(value: $instance) || !array_key_exists(key: $field, array: $instance)) {
            return null;
        }
        $value = $instance[$field];
        if (is_string(value: $value)) {
            return $value;
        }
        if (!is_array(value: $value)) {
            return null;
        }

        return array_values(array: array_filter(array: $value, callback: is_string(...)));
    }

    public function testOptionsFilterAcceptsKnownKeysOnlyAndReturnsAString(): void
    {
        $options = $this->intOptions();

        $known = $this->helper(post: ['f' => '2'])->checkOptionsFilter(formOptions: $options, fieldName: 'f');
        $unknown = $this->helper(post: ['f' => '9'])->checkOptionsFilter(formOptions: $options, fieldName: 'f');

        $this->assertSame('2', $known);
        $this->assertSame('2', $unknown);
    }

    public function testOptionsFilterAcceptsTheEmptyOptionAsNoFilter(): void
    {
        $options = $this->textOptions();
        $this->helper(post: ['f' => 'b'])->checkOptionsFilter(formOptions: $options, fieldName: 'f');

        $all = $this->helper(post: ['f' => ''])->checkOptionsFilter(formOptions: $options, fieldName: 'f');

        $this->assertSame('', $all);
        $this->assertSame('', $this->storedField(field: 'f'));
        $this->assertSame('', $this->helper()->checkOptionsFilter(formOptions: $options, fieldName: 'f'));
    }

    public function testOptionsFilterAcceptsTheEmptyOptionWithFind(): void
    {
        $options = $this->textOptions();
        $this->helper(post: ['f' => 'b'])->checkOptionsFilter(formOptions: $options, fieldName: 'f');

        $all = $this->helper(query: ['find' => ''], post: ['f' => ''])->checkOptionsFilter(
            formOptions: $options,
            fieldName: 'f',
            default: 'a',
        );

        $this->assertSame('', $all);
    }

    public function testOptionsFilterKeepsTheRememberedKeyForAnUnknownKey(): void
    {
        $options = $this->textOptions();
        $this->helper(post: ['f' => 'b'])->checkOptionsFilter(formOptions: $options, fieldName: 'f');

        $unknown = $this->helper(post: ['f' => 'x'])->checkOptionsFilter(formOptions: $options, fieldName: 'f');

        $this->assertSame('b', $unknown);
        $this->assertSame('b', $this->storedField(field: 'f'));
    }

    public function testOptionsFilterTakesTheDefaultAndIsResetByReset(): void
    {
        $options = $this->textOptions();
        $this->helper(post: ['f' => 'b'])->checkOptionsFilter(formOptions: $options, fieldName: 'f', default: 'a');

        $reset = $this->helper(query: ['reset' => ''])->checkOptionsFilter(
            formOptions: $options,
            fieldName: 'f',
            default: 'a',
        );

        $this->assertSame('a', $reset);
    }

    public function testIntOptionsFilterReturnsAnIntegerAndNullWithoutFilter(): void
    {
        $options = $this->intOptions();

        $this->assertNull($this->helper()->checkIntOptionsFilter(formOptions: $options, fieldName: 'f'));
        $posted = $this->helper(post: ['f' => '3'])->checkIntOptionsFilter(formOptions: $options, fieldName: 'f');
        $remembered = $this->helper()->checkIntOptionsFilter(formOptions: $options, fieldName: 'f');

        $this->assertSame(3, $posted);
        $this->assertSame(3, $remembered);
    }

    public function testIntOptionsFilterDefaultAndRememberedValueAreStrings(): void
    {
        $options = $this->intOptions();

        $helper = $this->helper(post: ['f' => '2']);
        $value = $helper->checkIntOptionsFilter(formOptions: $options, fieldName: 'f', default: 1);
        $this->assertSame(2, $value);
        $this->assertSame('2', $this->storedField(field: 'f'));

        $reset = $this->helper(query: ['reset' => ''])->checkIntOptionsFilter(
            formOptions: $options,
            fieldName: 'f',
            default: 1,
        );

        $this->assertSame(1, $reset);
    }

    public function testIntOptionsFilterReturnsNullForTheEmptyOption(): void
    {
        $options = $this->intOptions();
        $this->helper(post: ['f' => '2'])->checkIntOptionsFilter(formOptions: $options, fieldName: 'f');

        $all = $this->helper(post: ['f' => ''])->checkIntOptionsFilter(formOptions: $options, fieldName: 'f');

        $this->assertNull($all);
        $this->assertNull($this->helper()->checkIntOptionsFilter(formOptions: $options, fieldName: 'f'));
    }

    public function testIntOptionsFilterThrowsForTextOptions(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->helper(post: ['f' => 'a'])->checkIntOptionsFilter(formOptions: $this->textOptions(), fieldName: 'f');
    }

    public function testMultiOptionsFilterCollectsTheCheckedKeysAsStringsInTheOrderOfTheOptions(): void
    {
        $helper = $this->helper(query: ['find' => ''], post: ['groups' => ['3', '1', '9']]);

        $groups = $helper->checkMultiOptionsFilter(formOptions: $this->intOptions(), fieldName: 'groups');

        $this->assertSame(['1', '3'], $groups);
        $this->assertSame(['1', '3'], $this->storedField(field: 'groups'));
    }

    public function testMultiOptionsFilterIgnoresInputWithoutFindOrReset(): void
    {
        $helper = $this->helper(post: ['groups' => ['1']]);

        $groups = $helper->checkMultiOptionsFilter(
            formOptions: $this->intOptions(),
            fieldName: 'groups',
            default: ['2'],
        );

        $this->assertSame(['2'], $groups);
    }

    public function testMultiOptionsFilterRemembersAndDropsKeysThatAreNoOptionAnyMore(): void
    {
        $this->helper(query: ['find' => ''], post: ['groups' => ['1', '2']])->checkMultiOptionsFilter(
            formOptions: $this->intOptions(),
            fieldName: 'groups',
        );
        $smaller = new FormOptions();
        $smaller->addIntItem(key: 2, htmlText: HtmlText::fromHtml(html: 'Two'));

        $this->assertSame(['2'], $this->helper()->checkMultiOptionsFilter(formOptions: $smaller, fieldName: 'groups'));
    }

    public function testMultiOptionsFilterIsResetToTheDefault(): void
    {
        $options = $this->intOptions();
        $this->helper(query: ['find' => ''], post: ['groups' => ['1']])->checkMultiOptionsFilter(
            formOptions: $options,
            fieldName: 'groups',
        );

        $reset = $this->helper(query: ['reset' => ''])->checkMultiOptionsFilter(
            formOptions: $options,
            fieldName: 'groups',
            default: ['3'],
        );

        $this->assertSame(['3'], $reset);
    }

    public function testIntMultiOptionsFilterReturnsIntegers(): void
    {
        $options = $this->intOptions();
        $helper = $this->helper(query: ['find' => ''], post: ['groups' => ['2', '3']]);

        $posted = $helper->checkIntMultiOptionsFilter(formOptions: $options, fieldName: 'groups');
        $remembered = $this->helper()->checkIntMultiOptionsFilter(formOptions: $options, fieldName: 'groups');

        $this->assertSame([2, 3], $posted);
        $this->assertSame([2, 3], $remembered);
        $this->assertSame(
            [1],
            $this->helper(query: ['reset' => ''])->checkIntMultiOptionsFilter(
                formOptions: $options,
                fieldName: 'groups',
                default: [1],
            ),
        );
    }

    public function testIntMultiOptionsFilterThrowsForTextOptions(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->helper(query: ['find' => ''], post: ['groups' => ['a']])->checkIntMultiOptionsFilter(
            formOptions: $this->textOptions(),
            fieldName: 'groups',
        );
    }
}
