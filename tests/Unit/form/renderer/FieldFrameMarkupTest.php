<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\renderer;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\SelectOptionsField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormOptions;
use actra\yuf\form\renderer\CompactFieldRenderer;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\form\FormContextFactory;
use PHPUnit\Framework\TestCase;

/**
 * The frame around a field: the definition list of the default renderer (pinned, unchanged by the compact renderer)
 * and the compact renderer.
 */
final class FieldFrameMarkupTest extends TestCase
{
    private function createForm(): Form
    {
        return new Form(context: FormContextFactory::create(), name: 'search');
    }

    public function testDefinitionListFrameOfAPlainField(): void
    {
        $form = $this->createForm();
        $form->addField(formField: new TextField(name: 'q', label: HtmlText::fromHtml(html: 'Search')));

        $this->assertSame(
            '<form method="post" action="?search"><dl><dt><label for="q">Search</label></dt>'
            . '<dd><input type="text" name="q" id="q" value=""></dd></dl></form>',
            $form->render(),
        );
    }

    public function testDefinitionListFrameWithRequiredLabelInfoErrorAndFieldInfo(): void
    {
        $form = $this->createForm();
        $field = new TextField(
            name: 'q',
            label: HtmlText::fromHtml(html: 'Search'),
            requiredError: HtmlText::fromHtml(html: 'Required'),
        );
        $field->labelInfoText = HtmlText::fromHtml(html: '(info)');
        $field->fieldInfo = HtmlText::fromHtml(html: 'Help');
        $form->addField(formField: $field);
        $form->validate(input: FormInput::fromArray(data: ['q' => ''], query: ['search' => '']));

        $this->assertSame(
            '<form method="post" action="?search"><dl><dt><label for="q">Search<span class="required">*</span>'
            . '<i class="label-info">(info)</i></label></dt><dd class="has-error">'
            . '<input type="text" name="q" id="q" value="" aria-invalid="true" '
            . 'aria-describedby="q-error q-info"><div class="form-input-error" id="q-error" role="alert" '
            . 'aria-live="assertive">Required</div><div class="form-input-info" id="q-info">Help</div></dd></dl>'
            . '</form>',
            $form->render(),
        );
    }

    public function testDefinitionListFrameWithoutVisibleLabel(): void
    {
        $form = $this->createForm();
        $field = new TextField(name: 'q', label: HtmlText::fromHtml(html: 'Search'));
        $field->setRenderLabelFalse();
        $form->addField(formField: $field);

        $this->assertSame(
            '<form method="post" action="?search"><div class="form-toggle-content-item">'
            . '<label for="q" class="visuallyhidden">Search</label><input type="text" name="q" id="q" value="">'
            . '</div></form>',
            $form->render(),
        );
    }

    public function testCompactFrameIsLabelAndControl(): void
    {
        $form = $this->createForm();
        $form->useCompactFieldRenderer();
        $form->addField(formField: new TextField(name: 'q', label: HtmlText::fromHtml(html: 'Search')));

        $this->assertSame(
            '<form method="post" action="?search"><div><label for="q">Search</label>'
            . '<input type="text" name="q" id="q" value=""></div></form>',
            $form->render(),
        );
    }

    public function testCompactFrameWithRequiredErrorAndHiddenLabel(): void
    {
        $form = $this->createForm();
        $form->useCompactFieldRenderer();
        $field = new TextField(
            name: 'q',
            label: HtmlText::fromHtml(html: 'Search'),
            requiredError: HtmlText::fromHtml(html: 'Required'),
        );
        $field->setRenderLabelFalse();
        $form->addField(formField: $field);
        $form->validate(input: FormInput::fromArray(data: ['q' => ''], query: ['search' => '']));

        $this->assertSame(
            '<form method="post" action="?search"><div class="has-error">'
            . '<label for="q" class="visuallyhidden">Search<span class="required">*</span></label>'
            . '<input type="text" name="q" id="q" value="" aria-invalid="true" aria-describedby="q-error">'
            . '<div class="form-input-error" id="q-error" role="alert" aria-live="assertive">Required</div>'
            . '</div></form>',
            $form->render(),
        );
    }

    public function testCompactFrameEscapesTheLabel(): void
    {
        $form = $this->createForm();
        $form->useCompactFieldRenderer();
        $form->addField(formField: new TextField(name: 'q', label: HtmlText::fromText(text: 'A <b> & "c"')));

        $this->assertStringContainsString('<label for="q">A &lt;b&gt; &amp; &quot;c&quot;</label>', $form->render());
    }

    public function testCompactRendererPerFieldLeavesTheOtherFieldsInTheDefinitionList(): void
    {
        $form = $this->createForm();
        $compact = new TextField(name: 'a', label: HtmlText::fromHtml(html: 'A'));
        $compact->setRenderer(renderer: new CompactFieldRenderer(formField: $compact));
        $form->addField(formField: $compact);
        $form->addField(formField: new TextField(name: 'b', label: HtmlText::fromHtml(html: 'B')));

        $this->assertSame(
            '<form method="post" action="?search"><div><label for="a">A</label>'
            . '<input type="text" name="a" id="a" value=""></div><dl><dt><label for="b">B</label></dt>'
            . '<dd><input type="text" name="b" id="b" value=""></dd></dl></form>',
            $form->render(),
        );
    }

    public function testCompactFrameOfASelectField(): void
    {
        $form = $this->createForm();
        $form->useCompactFieldRenderer();
        $options = new FormOptions();
        $options->addIntItem(key: 7, htmlText: HtmlText::fromHtml(html: 'Seven'));
        $form->addField(
            formField: new SelectOptionsField(
                name: 'level',
                label: HtmlText::fromHtml(html: 'Level'),
                formOptions: $options,
                initialValue: '7',
            ),
        );

        $html = $form->render();

        $this->assertStringContainsString(
            '<div><label for="level">Level</label><select',
            $html,
        );
        $this->assertStringContainsString('<option value="7" selected>Seven</option>', $html);
    }

    public function testTheCompactRendererCanRenderTwice(): void
    {
        $form = $this->createForm();
        $form->useCompactFieldRenderer();
        $form->addField(formField: new TextField(name: 'q', label: HtmlText::fromHtml(html: 'Search')));

        $this->assertSame($form->render(), $form->render());
    }
}
