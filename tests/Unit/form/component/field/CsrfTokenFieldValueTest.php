<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\CsrfTokenField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\security\CsrfTokenSource;
use actra\yuf\tests\Double\security\InMemoryCsrfTokenSource;
use Override;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The field checks the posted token against the `CsrfTokenSource` (here an in-memory one, token `expected-token`).
 */
final class CsrfTokenFieldValueTest extends TestCase
{
    private function createField(string $token = 'expected-token'): CsrfTokenField
    {
        return new CsrfTokenField(tokenSource: new InMemoryCsrfTokenSource(token: $token));
    }

    /**
     * @param array<string, string|list<string>> $query
     * @param array<string, string|list<string>> $data
     */
    private function validate(CsrfTokenField $field, array $data, array $query = []): bool
    {
        return $field->validate(input: FormInput::fromArray(data: $data, query: $query));
    }

    public function testNameIsTheCsrfFieldName(): void
    {
        $this->assertSame('csrftoken', $this->createField()->name);
    }

    public function testFieldHasNoGetter(): void
    {
        $reflection = new ReflectionClass(objectOrClass: CsrfTokenField::class);

        $this->assertFalse($reflection->hasMethod(name: 'getValueAsString'));
    }

    public function testFieldHasNoInitialValue(): void
    {
        $reflection = new ReflectionClass(objectOrClass: CsrfTokenField::class);

        $this->assertFalse($reflection->hasMethod(name: 'setInitialValue'));
    }

    public function testFieldHasNoSetter(): void
    {
        $reflection = new ReflectionClass(objectOrClass: CsrfTokenField::class);

        $this->assertFalse($reflection->hasMethod(name: 'setValue'));
    }

    public function testValidTokenIsAccepted(): void
    {
        $field = $this->createField();

        $this->assertTrue($this->validate(field: $field, data: ['csrftoken' => 'expected-token']));
        $this->assertFalse($field->hasErrors(withChildElements: true));
    }

    public function testWrongTokenAddsOneErrorWithTheMessageOfTheField(): void
    {
        $field = $this->createField();

        $isValid = $this->validate(field: $field, data: ['csrftoken' => 'abc']);

        $this->assertFalse($isValid);
        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame(
            'The form could not be submitted because of a technical problem (invalid CSRF token). Please try again.',
            $field->errorCollection->getFirstError()->render(),
        );
    }

    public function testErrorUsesTheGermanMessageIfTheFieldHasThem(): void
    {
        $field = $this->createField();
        $field->messages = FormMessages::german();

        $this->validate(field: $field, data: ['csrftoken' => 'abc']);

        $this->assertStringStartsWith(
            'Das Formular konnte wegen eines technischen Problems',
            $field->errorCollection->getFirstError()->render(),
        );
    }

    public function testMissingTokenIsInvalid(): void
    {
        $field = $this->createField();

        $this->assertFalse($this->validate(field: $field, data: []));
        $this->assertSame(1, $field->errorCollection->count());
    }

    public function testEmptyTokenIsInvalid(): void
    {
        $this->assertFalse($this->validate(field: $this->createField(), data: ['csrftoken' => '']));
    }

    public function testTokenIsNotTrimmed(): void
    {
        $this->assertFalse($this->validate(field: $this->createField(), data: ['csrftoken' => ' expected-token ']));
    }

    public function testZeroWidthSpacesAreRemovedFromThePostedToken(): void
    {
        $isValid = $this->validate(field: $this->createField(), data: ['csrftoken' => "expected\u{200B}-token"]);

        $this->assertTrue($isValid);
    }

    public function testMissingPostedTokenFallsBackToTheQueryString(): void
    {
        $field = $this->createField();

        $isValid = $this->validate(field: $field, data: [], query: ['csrftoken' => 'expected-token']);

        $this->assertTrue($isValid);
    }

    public function testWrongTokenInTheQueryStringIsInvalid(): void
    {
        $isValid = $this->validate(field: $this->createField(), data: [], query: ['csrftoken' => 'abc']);

        $this->assertFalse($isValid);
    }

    public function testPostedTokenWinsOverTheQueryString(): void
    {
        $field = $this->createField();

        $isValid = $this->validate(
            field: $field,
            data: ['csrftoken' => 'abc'],
            query: ['csrftoken' => 'expected-token'],
        );

        $this->assertFalse($isValid);
    }

    public function testEmptyPostedTokenDoesNotFallBackToTheQueryString(): void
    {
        $isValid = $this->validate(
            field: $this->createField(),
            data: ['csrftoken' => ''],
            query: ['csrftoken' => 'expected-token'],
        );

        $this->assertFalse($isValid);
    }

    public function testQueryStringTokenOfAnArrayIsIgnored(): void
    {
        $isValid = $this->validate(field: $this->createField(), data: [], query: ['csrftoken' => ['expected-token']]);

        $this->assertFalse($isValid);
    }

    public function testArrayInputIsRejectedWithOneError(): void
    {
        $field = $this->createField();

        $isValid = $this->validate(field: $field, data: ['csrftoken' => ['expected-token']]);

        $this->assertFalse($isValid);
        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('The invalid input was ignored.', $field->errorCollection->getFirstError()->render());
    }

    public function testArrayInputIsNotReplacedByTheQueryString(): void
    {
        $isValid = $this->validate(
            field: $this->createField(),
            data: ['csrftoken' => ['x']],
            query: ['csrftoken' => 'expected-token'],
        );

        $this->assertFalse($isValid);
    }

    public function testInputWithoutQueryStringHasNoTokenFallback(): void
    {
        $field = $this->createField();

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['csrftoken' => 'expected-token'])));
        $this->assertFalse($this->createField()->validate(input: FormInput::fromArray(data: [])));
    }

    public function testEachValidationChecksTheNewInput(): void
    {
        $field = $this->createField();
        $this->validate(field: $field, data: ['csrftoken' => 'abc']);

        $this->validate(field: $field, data: ['csrftoken' => 'expected-token']);

        $this->assertSame(1, $field->errorCollection->count());
    }

    public function testRenderingShowsTheTokenOfTheSource(): void
    {
        $this->assertSame(
            '<input type="hidden" name="csrftoken" value="expected-token">',
            $this->createField()->render(),
        );
    }

    public function testRenderingEncodesTheToken(): void
    {
        $this->assertSame(
            '<input type="hidden" name="csrftoken" value="a&quot;&lt;b+/=">',
            $this->createField(token: 'a"<b+/=')->render(),
        );
    }

    public function testRenderingNeverShowsThePostedToken(): void
    {
        $field = $this->createField();
        $this->validate(field: $field, data: ['csrftoken' => 'posted-token']);

        $html = $field->render();

        $this->assertStringNotContainsString('posted-token', $html);
        $this->assertStringContainsString('value="expected-token"', $html);
    }

    public function testTokenIsOnlyReadFromTheSourceWhenNeeded(): void
    {
        $source = new class implements CsrfTokenSource {
            public int $calls = 0;

            #[Override]
            public function getToken(): string
            {
                $this->calls++;

                return 'token';
            }

            #[Override]
            public function isValid(string $token): bool
            {
                $this->calls++;

                return false;
            }
        };

        $field = new CsrfTokenField(tokenSource: $source);
        $this->assertSame(0, $source->calls);

        $field->render();
        $this->assertSame(1, $source->calls);
    }
}
