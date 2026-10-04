<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\renderer;

use actra\yuf\form\component\field\HiddenField;
use actra\yuf\form\component\field\TextAreaField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;

/**
 * InputFieldRenderer, HiddenFieldRenderer and TextAreaRenderer render the typed string value of their field.
 */
final class TextualFieldRenderersTest extends TestCase
{
    public function testInputFieldRendersEncodedValue(): void
    {
        $field = new TextField(name: 'field', label: HtmlText::encoded(textContent: 'Label'), value: 'a"<b');

        $this->assertStringContainsString('value="a&quot;&lt;b"', $field->render());
    }

    public function testInputFieldRendersPostedValueTrimmed(): void
    {
        $field = new TextField(name: 'field', label: HtmlText::encoded(textContent: 'Label'));
        $field->validate(inputData: ['field' => ' posted ']);

        $this->assertStringContainsString('value="posted"', $field->render());
    }

    public function testHiddenFieldRendersItsValue(): void
    {
        $field = new HiddenField(name: 'hidden', value: '7');

        $html = $field->render();

        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('value="7"', $html);
    }

    public function testTextAreaRendersEncodedTextWithoutTrimming(): void
    {
        $field = new TextAreaField(name: 'text', label: HtmlText::encoded(textContent: 'Label'), value: " a<\n b ");

        $this->assertStringContainsString('>' . " a&lt;\n b " . '</textarea>', $field->render());
    }

    public function testTextAreaRendersPostedText(): void
    {
        $field = new TextAreaField(name: 'text', label: HtmlText::encoded(textContent: 'Label'));
        $field->validate(inputData: ['text' => "x\ny"]);

        $this->assertStringContainsString(">x\ny</textarea>", $field->render());
    }
}