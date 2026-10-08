<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\renderer;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\FileField;
use actra\yuf\form\component\field\SelectOptionsField;
use actra\yuf\form\component\field\TextAreaField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\form\FormOptions;
use actra\yuf\form\model\UploadedFile;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\form\FormContextFactory;
use actra\yuf\tests\Double\form\InMemoryFileUploadStorage;
use PHPUnit\Framework\TestCase;

/**
 * The renderers pass every value that is plain text to `HtmlTagAttribute::fromText()`: field names, ids, option keys,
 * placeholders, CSS classes, data attributes and links are escaped, not output as they are.
 */
final class AttributeEscapingTest extends TestCase
{
    private function label(): HtmlText
    {
        return HtmlText::fromText(text: 'Label');
    }

    public function testPlaceholderOfAnInputIsEscaped(): void
    {
        $field = new TextField(name: 't', label: $this->label(), placeholder: 'Name & "Vorname" <x>');

        $this->assertStringContainsString(
            'placeholder="Name &amp; &quot;Vorname&quot; &lt;x&gt;"',
            $field->getHtmlTag()->render(),
        );
    }

    public function testPlaceholderOfATextAreaIsEscaped(): void
    {
        $field = new TextAreaField(name: 'a', label: $this->label());
        $field->setPlaceholder(placeholder: 'a "b" & c');

        $this->assertStringContainsString('placeholder="a &quot;b&quot; &amp; c"', $field->getHtmlTag()->render());
    }

    public function testNameAndIdOfAFieldAreEscaped(): void
    {
        $field = new TextField(name: 'a"b&c', label: $this->label());

        $this->assertStringContainsString('name="a&quot;b&amp;c" id="a&quot;b&amp;c"', $field->getHtmlTag()->render());
    }

    public function testOptionKeysClassesAndDataAttributesOfASelectAreEscaped(): void
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: 'a"b&c', htmlText: HtmlText::fromText(text: 'A'));
        $field = new SelectOptionsField(
            name: 's',
            label: $this->label(),
            formOptions: $formOptions,
            initialValue: null,
            cssClasses: ['x"y'],
            renderEmptyValueOption: false,
        );
        $field->addDataAttribute(name: 'note', value: 'a & "b"');
        $field->addDataAttribute(name: 'flag', value: '');

        $html = $field->getHtmlTag()->render();

        $this->assertStringContainsString('<option value="a&quot;b&amp;c">A</option>', $html);
        $this->assertStringContainsString('class="x&quot;y"', $html);
        $this->assertStringContainsString('data-note="a &amp; &quot;b&quot;" data-flag id="s"', $html);
    }

    public function testCancelLinkAndNameOfAFormControlAreEscaped(): void
    {
        $control = new FormControl(
            name: 'send',
            submitLabel: HtmlText::fromText(text: 'Send'),
            cancelLink: '/list?a=1&b="2"',
        );

        $html = $control->getHtmlTag()->render();

        $this->assertStringContainsString('name="send"', $html);
        $this->assertStringContainsString('href="/list?a=1&amp;b=&quot;2&quot;"', $html);
    }

    public function testCancelLabelFromTheMessagesIsPlainText(): void
    {
        $control = new FormControl(
            name: 'send',
            submitLabel: HtmlText::fromText(text: 'Send'),
            cancelLink: '/x',
        );
        $control->messages = new FormMessages(cancel: 'Don\'t <save>');

        $this->assertStringContainsString('>Don&#039;t &lt;save&gt;</a>', $control->getHtmlTag()->render());
    }

    public function testFormActionAndCssClassesAreEscaped(): void
    {
        $form = new Form(
            context: FormContextFactory::create(),
            name: 'f',
            individualSentIndicator: 'a&b',
        );
        $form->addCssClass(className: 'c"d');

        $this->assertStringStartsWith(
            '<form method="post" action="?a&amp;b" class="c&quot;d">',
            $form->getHtmlTag()->render(),
        );
    }

    public function testNameOfTheRemoveButtonOfAFileFieldIsEncoded(): void
    {
        $storage = new InMemoryFileUploadStorage();
        $storage->preload(
            'ptr1',
            new UploadedFile(name: 'first.txt', type: 'text/plain', size: 1, path: '/tmp/yuf-test/a'),
        );
        $field = new FileField(name: 'f"g', label: $this->label(), storage: $storage);
        $field->validate(input: FormInput::fromArray(data: ['f"g_UID' => 'ptr1']));

        $html = $field->getHtmlTag()->render();

        $this->assertStringContainsString('<button type="submit" name="f&quot;g_removeAttachment" value="', $html);
        $this->assertStringNotContainsString('f"g', $html);
    }
}
