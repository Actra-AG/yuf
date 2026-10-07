<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form;

use actra\yuf\common\TimeOfDay;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\BooleanField;
use actra\yuf\form\component\field\CheckboxOptionsField;
use actra\yuf\form\component\field\DateField;
use actra\yuf\form\component\field\DecimalField;
use actra\yuf\form\component\field\EmailField;
use actra\yuf\form\component\field\FileField;
use actra\yuf\form\component\field\IntegerField;
use actra\yuf\form\component\field\MultiSelectOptionsField;
use actra\yuf\form\component\field\PasswordField;
use actra\yuf\form\component\field\SelectOptionsField;
use actra\yuf\form\component\field\TextAreaField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\field\TimeField;
use actra\yuf\form\component\field\ToggleField;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\form\FormNameRegistry;
use actra\yuf\form\FormOptions;
use actra\yuf\form\rule\IntegerMinRule;
use actra\yuf\form\rule\MinLengthRule;
use actra\yuf\form\settings\PasswordPurposeEnum;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\form\InMemoryFileUploadStorage;
use actra\yuf\tests\Double\security\InMemoryCsrfTokenSource;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * One form with every kind of field: built, validated with a `FormInput` (valid and invalid), read through the typed
 * getters and rendered. Proves that the pieces of the v4 form API work together.
 */
final class FormEndToEndTest extends TestCase
{
    private Form $form;
    private TextField $name;
    private EmailField $email;
    private PasswordField $password;
    private IntegerField $quantity;
    private DecimalField $price;
    private DateField $birthday;
    private TimeField $pickupTime;
    private SelectOptionsField $country;
    private MultiSelectOptionsField $languages;
    private CheckboxOptionsField $interests;
    private BooleanField $newsletter;
    private BooleanField $terms;
    private ToggleField $delivery;
    private TextField $street;
    private TextAreaField $message;
    private FileField $attachment;
    private InMemoryFileUploadStorage $storage;

    protected function setUp(): void
    {
        FormNameRegistry::reset();
        $this->storage = new InMemoryFileUploadStorage();
        $this->form = new Form(
            name: 'order',
            acceptUpload: true,
            messages: FormMessages::german(),
            csrfTokenSource: new InMemoryCsrfTokenSource(token: 'expected-token'),
        );
        $this->addFields();
    }

    private function text(string $text): HtmlText
    {
        return HtmlText::encoded(textContent: $text);
    }

    private function addFields(): void
    {
        $required = $this->text('Required');
        $invalid = $this->text('Invalid');
        $countries = new FormOptions();
        $countries->addItem(key: 'CH', htmlText: $this->text('Switzerland'));
        $countries->addItem(key: 'DE', htmlText: $this->text('Germany'));
        $countries->addItem(key: 'AT', htmlText: $this->text('Austria'));
        $deliveries = new FormOptions();
        $deliveries->addItem(key: 'pickup', htmlText: $this->text('Pickup'));
        $deliveries->addItem(key: 'post', htmlText: $this->text('Post'));

        $this->name = new TextField(name: 'customer', label: $this->text('Name'), requiredError: $required);
        $this->name->addRule(formRule: new MinLengthRule(minLength: 3, errorMessage: $this->text('Too short')));
        $this->email = new EmailField(
            name: 'email',
            label: $this->text('Email'),
            value: null,
            invalidError: $invalid,
            requiredError: $required,
            dnsCheck: false,
        );
        $this->password = new PasswordField(
            name: 'secret',
            label: $this->text('Password'),
            requiredError: $required,
            purpose: PasswordPurposeEnum::NEW,
        );
        $this->quantity = new IntegerField(name: 'quantity', label: $this->text('Quantity'), requiredError: $required);
        $this->quantity->addValueRule(formRule: new IntegerMinRule(min: 1, errorMessage: $this->text('At least 1')));
        $this->price = new DecimalField(name: 'price', label: $this->text('Price'), scale: 2);
        $this->birthday = new DateField(
            name: 'birthday',
            label: $this->text('Birthday'),
            value: null,
            invalidError: $invalid,
        );
        $this->pickupTime = new TimeField(
            name: 'pickupTime',
            label: $this->text('Time'),
            value: null,
            invalidError: $invalid,
        );
        $this->country = new SelectOptionsField(
            name: 'country',
            label: $this->text('Country'),
            formOptions: $countries,
            initialValue: null,
            requiredError: $required,
        );
        $this->languages = new MultiSelectOptionsField(
            name: 'languages',
            label: $this->text('Languages'),
            formOptions: $countries,
            initialValues: [],
        );
        $this->interests = new CheckboxOptionsField(
            name: 'interests',
            label: $this->text('Interests'),
            formOptions: $countries,
            initialValues: ['AT'],
        );
        $this->newsletter = new BooleanField(
            name: 'newsletter',
            label: $this->text('Newsletter'),
            isCheckedByDefault: false,
        );
        $this->terms = new BooleanField(
            name: 'terms',
            label: $this->text('Terms'),
            isCheckedByDefault: false,
            requiredError: $this->text('Accept the terms'),
        );
        $this->delivery = new ToggleField(
            name: 'delivery',
            label: $this->text('Delivery'),
            formOptions: $deliveries,
            initialValue: 'pickup',
        );
        $this->street = new TextField(name: 'street', label: $this->text('Street'), requiredError: $required);
        $this->delivery->addChildField(mainOption: 'post', childField: $this->street);
        $this->message = new TextAreaField(name: 'message', label: $this->text('Message'));
        $this->attachment = new FileField(
            name: 'attachment',
            label: $this->text('Attachment'),
            maxFileUploadCount: 2,
            storage: $this->storage,
        );

        foreach (
            [
                $this->name,
                $this->email,
                $this->password,
                $this->quantity,
                $this->price,
                $this->birthday,
                $this->pickupTime,
                $this->country,
                $this->languages,
                $this->interests,
                $this->newsletter,
                $this->terms,
                $this->delivery,
                $this->message,
                $this->attachment,
            ] as $field
        ) {
            $this->form->addField(formField: $field);
        }
        $this->form->addComponent(
            formComponent: new FormControl(
                name: 'submit',
                submitLabel: $this->text('Send'),
                cancelLink: '/orders',
            ),
        );
    }

    /**
     * @return array<string, string|list<string>>
     */
    private function validPost(): array
    {
        return [
            'customer' => '  Ann Example ',
            'email' => 'Ann@Example.com',
            'secret' => ' s3cret pass ',
            'quantity' => ' 3 ',
            'price' => '12.5',
            'birthday' => '2020-02-29',
            'pickupTime' => '08:30',
            'country' => 'CH',
            'languages' => ['DE', 'AT'],
            'interests' => ['CH', 'DE'],
            'newsletter' => ['checked'],
            'terms' => ['checked'],
            'delivery' => 'post',
            'street' => 'Main street 1',
            'message' => "line one\r\n\r\n line two ",
            'csrftoken' => 'expected-token',
        ];
    }

    /**
     * @param array<string, string|list<string>> $post
     */
    private function send(array $post, bool $withUpload = false): bool
    {
        $files = $withUpload ? [
            'attachment' => [
                'name' => ['cv.pdf'],
                'type' => ['application/pdf'],
                'tmp_name' => ['/tmp/phpUpload'],
                'error' => [UPLOAD_ERR_OK],
                'size' => [1234],
            ],
        ] : [];

        return $this->form->validate(
            input: FormInput::fromArray(data: $post, files: $files, query: ['order' => '']),
        );
    }

    public function testValidRequestPassesAndEveryFieldReturnsItsTypedValue(): void
    {
        $this->assertTrue($this->send(post: $this->validPost(), withUpload: true));

        $this->assertSame('Ann Example', $this->name->getValueAsString());
        $this->assertSame('ann@example.com', $this->email->getValueAsString());
        $this->assertSame(' s3cret pass ', $this->password->getValueAsString());
        $this->assertSame(3, $this->quantity->getValueAsInt());
        $this->assertSame('12.50', $this->price->getValueAsDecimal());
        $this->assertSame('2020-02-29', $this->birthday->getValueAsDateTimeImmutable()?->format(format: 'Y-m-d'));
        $this->assertTrue($this->pickupTime->getValueAsTimeOfDay()?->equals(other: new TimeOfDay(hour: 8, minute: 30)));
        $this->assertSame('CH', $this->country->getValueAsString());
        $this->assertSame(['DE', 'AT'], $this->languages->getValues());
        $this->assertSame(['CH', 'DE'], $this->interests->getValues());
        $this->assertTrue($this->newsletter->isChecked());
        $this->assertTrue($this->terms->isChecked());
        $this->assertSame('post', $this->delivery->getValueAsString());
        $this->assertSame('Main street 1', $this->street->getValueAsString());
        $this->assertSame(['line one', 'line two'], $this->message->getValues());
        $this->assertCount(1, $this->attachment->getFiles());
        $this->assertSame('cv.pdf', array_values(array: $this->attachment->getFiles())[0]->name);
    }

    public function testInitialAndAddedValuesAreTracked(): void
    {
        $this->send(post: $this->validPost());

        $this->assertTrue($this->interests->valueHasChanged());
        $this->assertSame(['CH', 'DE'], $this->interests->getAddedValues());
        $this->assertSame(['AT'], $this->interests->getRemovedValues());
        $this->assertTrue($this->name->valueHasChanged());
    }

    public function testInvalidRequestFailsWithAnErrorPerField(): void
    {
        $post = $this->validPost();
        $post['customer'] = 'Al';
        $post['email'] = 'not an address';
        $post['secret'] = '';
        $post['quantity'] = '0';
        $post['price'] = '12.555';
        $post['birthday'] = '2021-02-29';
        $post['pickupTime'] = '25:00';
        $post['country'] = 'XX';
        $post['languages'] = 'DE';
        $post['terms'] = [];
        $post['street'] = '';

        $this->assertFalse($this->send(post: $post));

        foreach (
            [
                $this->name,
                $this->email,
                $this->password,
                $this->quantity,
                $this->price,
                $this->birthday,
                $this->pickupTime,
                $this->country,
                $this->languages,
                $this->terms,
                $this->street,
            ] as $field
        ) {
            $this->assertTrue($field->hasErrors(withChildElements: false), 'No error for field ' . $field->name);
        }
        $this->assertFalse($this->interests->hasErrors(withChildElements: false));
        $this->assertSame('', $this->country->getValueAsString());
        $this->assertSame([], $this->languages->getValues());
        $this->assertSame('', $this->street->getValueAsString());
    }

    public function testInvalidTypedValuesThrowInTheGetter(): void
    {
        $post = $this->validPost();
        $post['quantity'] = 'abc';
        $this->send(post: $post);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('quantity');
        $this->quantity->getValueAsInt();
    }

    public function testWrongCsrfTokenFailsTheForm(): void
    {
        $post = $this->validPost();
        $post['csrftoken'] = 'wrong';

        $this->assertFalse($this->send(post: $post));
        $this->assertSame(
            FormMessages::german()->invalidCsrfToken,
            $this->form->errorCollection->getFirstError()->render(),
        );
    }

    public function testFormIsRenderedWithTheMarkupOfEveryField(): void
    {
        $this->send(post: $this->validPost(), withUpload: true);

        $html = $this->form->render();

        $this->assertStringContainsString('<form method="post" action="?order" enctype="multipart/form-data">', $html);
        foreach (
            [
                'name="customer" id="customer"',
                'type="email"',
                'autocomplete="new-password"',
                'name="quantity"',
                'value="12.50"',
                'type="text"',
                '<select',
                'multiple',
                'type="checkbox"',
                'name="newsletter[]"',
                'name="delivery"',
                'class="form-toggle-content"',
                '<textarea',
                'type="file"',
                'name="csrftoken" value="expected-token"',
                'class="form-control"',
                'class="link-cancel"',
                'Abbrechen',
            ] as $expected
        ) {
            $this->assertStringContainsString($expected, $html);
        }
        $this->assertStringNotContainsString('s3cret', $html);
    }

    public function testFormWithErrorsIsRenderedWithTheErrors(): void
    {
        $post = $this->validPost();
        $post['customer'] = '';
        $this->send(post: $post);

        $html = $this->form->render();

        $this->assertStringContainsString('class="form-input-error"', $html);
        $this->assertStringContainsString('Required', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
    }

    public function testBirthdayRoundTripsAsDateTimeImmutable(): void
    {
        $this->birthday->setValue(value: new DateTimeImmutable(datetime: '2000-01-02'));

        $this->assertStringContainsString('value="2000-01-02"', $this->form->render());
    }
}
