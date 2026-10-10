<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form;

use actra\yuf\form\FormOption;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class FormOptionsTest extends TestCase
{
    public function testIsFinal(): void
    {
        $this->assertTrue(new ReflectionClass(objectOrClass: FormOptions::class)->isFinal());
    }

    public function testExistsFindsAddedKeysOnly(): void
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: 'a', htmlText: HtmlText::fromHtml(html: 'A'));

        $this->assertTrue($formOptions->exists(key: 'a'));
        $this->assertFalse($formOptions->exists(key: 'b'));
        $this->assertFalse($formOptions->exists(key: 'A'));
    }

    public function testNumericAndEmptyKeys(): void
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: '0', htmlText: HtmlText::fromHtml(html: 'Zero'));
        $formOptions->addItem(key: '', htmlText: HtmlText::fromHtml(html: 'Empty'));

        $this->assertTrue($formOptions->exists(key: '0'));
        $this->assertTrue($formOptions->exists(key: ''));
        $this->assertFalse($formOptions->exists(key: '1'));
    }

    public function testGetKeysReturnsStringsAlsoForNumericKeys(): void
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: '7', htmlText: HtmlText::fromHtml(html: 'Seven'));
        $formOptions->addItem(key: 'x', htmlText: HtmlText::fromHtml(html: 'X'));
        $formOptions->addItem(key: '07', htmlText: HtmlText::fromHtml(html: 'Zero seven'));

        $this->assertSame(['7', 'x', '07'], $formOptions->getKeys());
    }

    public function testAddIntItemIsTheSameOptionAsItsDecimalText(): void
    {
        $formOptions = new FormOptions();
        $formOptions->addIntItem(key: 12, htmlText: HtmlText::fromHtml(html: 'Twelve'));
        $formOptions->addIntItem(key: -3, htmlText: HtmlText::fromHtml(html: 'Minus three'));

        $this->assertTrue($formOptions->exists(key: '12'));
        $this->assertTrue($formOptions->exists(key: '-3'));
        $this->assertFalse($formOptions->exists(key: '012'));
        $this->assertSame(['12', '-3'], $formOptions->getKeys());
    }

    public function testAddIntItemReplacesTheTextItemWithTheSameKey(): void
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: '5', htmlText: HtmlText::fromHtml(html: 'Text'));
        $formOptions->addIntItem(key: 5, htmlText: HtmlText::fromHtml(html: 'Int'));

        $texts = array_map(
            callback: static fn(FormOption $item): string => $item->htmlText->render(),
            array: $formOptions->getItems(),
        );
        $this->assertSame(['Int'], $texts);
    }

    public function testGetItemsKeepsTheOrderAndHasStringKeys(): void
    {
        $formOptions = new FormOptions();
        $formOptions->addIntItem(key: 2, htmlText: HtmlText::fromHtml(html: 'Two'));
        $formOptions->addItem(key: 'a', htmlText: HtmlText::fromHtml(html: 'A'));
        $formOptions->addIntItem(key: 1, htmlText: HtmlText::fromHtml(html: 'One'));

        $items = $formOptions->getItems();

        $keys = array_map(callback: static fn(FormOption $item): string => $item->key, array: $items);
        $this->assertSame(['2', 'a', '1'], $keys);
        $this->assertSame(
            ['Two', 'A', 'One'],
            array_map(callback: static fn(FormOption $item): string => $item->htmlText->render(), array: $items),
        );
    }
}
