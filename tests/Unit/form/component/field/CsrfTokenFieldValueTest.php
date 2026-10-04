<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\CsrfTokenField;
use PHPUnit\Framework\TestCase;

/**
 * Not covered: getHtmlTag() sets the value from the session token ($_SESSION, random token).
 * Before rendering, the field behaves like a HiddenField.
 */
final class CsrfTokenFieldValueTest extends TestCase
{
    public function testValueIsEmptyStringAfterConstruction(): void
    {
        $this->assertSame('', new CsrfTokenField()->getRawValue());
    }

    public function testStringInputIsStored(): void
    {
        $field = new CsrfTokenField();

        $field->validate(inputData: ['csrftoken' => 'abc']);

        $this->assertSame('abc', $field->getRawValue());
    }

    public function testValueIsEmptyStringAfterValidationWithMissingKey(): void
    {
        $field = new CsrfTokenField();

        $field->validate(inputData: []);

        $this->assertSame('', $field->getRawValue());
    }
}