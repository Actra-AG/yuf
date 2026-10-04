<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form;

use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class FormOptionsTest extends TestCase
{
    public function testIsFinal(): void
    {
        $this->assertTrue((new ReflectionClass(objectOrClass: FormOptions::class))->isFinal());
    }

    public function testExistsFindsAddedKeysOnly(): void
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: 'a', htmlText: HtmlText::encoded(textContent: 'A'));

        $this->assertTrue($formOptions->exists(key: 'a'));
        $this->assertFalse($formOptions->exists(key: 'b'));
        $this->assertFalse($formOptions->exists(key: 'A'));
    }

    public function testNumericAndEmptyKeys(): void
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: '0', htmlText: HtmlText::encoded(textContent: 'Zero'));
        $formOptions->addItem(key: '', htmlText: HtmlText::encoded(textContent: 'Empty'));

        $this->assertTrue($formOptions->exists(key: '0'));
        $this->assertTrue($formOptions->exists(key: ''));
        $this->assertFalse($formOptions->exists(key: '1'));
    }

    public function testDataKeepsTheOrderOfTheItems(): void
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: 'b', htmlText: HtmlText::encoded(textContent: 'B'));
        $formOptions->addItem(key: 'a', htmlText: HtmlText::encoded(textContent: 'A'));

        $this->assertSame(['b', 'a'], array_map(callback: 'strval', array: array_keys(array: $formOptions->data)));
    }
}