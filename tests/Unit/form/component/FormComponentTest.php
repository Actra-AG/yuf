<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component;

use actra\yuf\form\component\collection\ErrorCollection;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\component\FormInfo;
use actra\yuf\form\component\FormSubHeadline;
use actra\yuf\form\FormMessages;
use actra\yuf\form\FormNameRegistry;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\security\InMemoryCsrfTokenSource;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * The components besides the fields: errors, control buttons, info and headline.
 */
final class FormComponentTest extends TestCase
{
    protected function setUp(): void
    {
        FormNameRegistry::reset();
    }

    private function text(string $text): HtmlText
    {
        return HtmlText::encoded(textContent: $text);
    }

    private function createControl(?HtmlText $cancelLabel = null): FormControl
    {
        return new FormControl(
            name: 'control',
            submitLabel: $this->text('Save'),
            cancelLink: '/back',
            cancelLabel: $cancelLabel
        );
    }

    public function testAddErrorStoresTheHtmlTextAndMarksTheParents(): void
    {
        $form = new Form(name: 'errors', csrfTokenSource: new InMemoryCsrfTokenSource());
        $control = $this->createControl();
        $form->addComponent(formComponent: $control);

        $control->addError(errorMessage: HtmlText::unencoded(textContent: 'a < b'));

        $this->assertTrue($control->hasErrors(withChildElements: false));
        $this->assertTrue($form->hasErrors(withChildElements: true));
        $this->assertFalse($form->hasErrors(withChildElements: false));
        $this->assertSame('a &lt; b', $control->errorCollection->getFirstError()->render());
    }

    public function testErrorCollectionKeepsTheOrderAndRefusesAFirstErrorWithoutErrors(): void
    {
        $collection = new ErrorCollection();
        $this->assertFalse($collection->hasErrors());

        $collection->add(errorMessageObject: $this->text('one'));
        $collection->add(errorMessageObject: $this->text('two'));

        $this->assertSame(2, $collection->count());
        $this->assertSame('one', $collection->getFirstError()->render());
        $this->assertSame(['one', 'two'], array_map(
            callback: static fn(HtmlText $error): string => $error->render(),
            array: $collection->listErrors()
        ));
        $this->expectException(LogicException::class);
        (new ErrorCollection())->getFirstError();
    }

    public function testControlRendersTheEnglishCancelTextWithoutAForm(): void
    {
        $this->assertSame(
            '<div class="form-control"><button type="submit" name="control">Save</button>'
            . '<a href="/back" class="link-cancel">Cancel</a></div>',
            $this->createControl()->render()
        );
    }

    public function testControlGetsTheCancelTextOfTheForm(): void
    {
        $form = new Form(
            name: 'german',
            messages: FormMessages::german(),
            csrfTokenSource: new InMemoryCsrfTokenSource()
        );
        $control = $this->createControl();
        $form->addComponent(formComponent: $control);

        $this->assertStringContainsString('>Abbrechen</a>', $control->render());
    }

    public function testControlAddedAsChildComponentUsesTheMessagesOfTheForm(): void
    {
        $form = new Form(
            name: 'childComponent',
            messages: FormMessages::german(),
            csrfTokenSource: new InMemoryCsrfTokenSource()
        );
        $control = $this->createControl();
        $form->addChildComponent(formComponent: $control);

        $this->assertStringContainsString('>Abbrechen</a>', $control->render());
    }

    public function testIndividualCancelLabelWinsOverTheMessages(): void
    {
        $form = new Form(
            name: 'individual',
            messages: FormMessages::german(),
            csrfTokenSource: new InMemoryCsrfTokenSource()
        );
        $control = $this->createControl(cancelLabel: $this->text('Back'));
        $form->addComponent(formComponent: $control);

        $this->assertStringContainsString('>Back</a>', $control->render());
    }

    public function testControlWithoutCancelLinkHasNoCancelLink(): void
    {
        $control = new FormControl(name: 'control', submitLabel: $this->text('Save'));

        $this->assertStringNotContainsString('link-cancel', $control->render());
    }

    public function testInfoRendersTheClassLists(): void
    {
        $info = new FormInfo(
            title: $this->text('Title'),
            content: $this->text('Content'),
            dlClasses: ['dl-a', 'dl-b'],
            dtClasses: ['dt-a'],
            ddClasses: ['dd-a']
        );

        $this->assertSame(
            '<dl class="dl-a dl-b"><dt class="dt-a">Title</dt><dd class="dd-a">Content</dd></dl>',
            $info->render()
        );
    }

    public function testSubHeadlineRendersAHeading(): void
    {
        $this->assertSame(
            '<h3>Contact</h3>',
            (new FormSubHeadline(headingLevel: 3, content: $this->text('Contact')))->render()
        );
    }
}