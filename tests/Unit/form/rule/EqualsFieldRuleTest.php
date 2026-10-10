<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\rule;

use actra\yuf\form\component\field\PasswordField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\FormInput;
use actra\yuf\form\rule\EqualsFieldRule;
use actra\yuf\form\settings\PasswordPurposeEnum;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;

/**
 * A rule that compares the text with the current value of another field.
 */
final class EqualsFieldRuleTest extends TestCase
{
    private function createPassword(string $name): PasswordField
    {
        return new PasswordField(
            name: $name,
            label: HtmlText::fromHtml(html: $name),
            requiredError: HtmlText::fromHtml(html: 'Required'),
            purpose: PasswordPurposeEnum::NEW,
        );
    }

    private function createConfirmation(PasswordField $password): PasswordField
    {
        $confirmation = $this->createPassword(name: 'confirm');
        $confirmation->addRule(
            formRule: new EqualsFieldRule(otherField: $password, errorMessage: HtmlText::fromHtml(html: 'Different')),
        );

        return $confirmation;
    }

    public function testEqualValuesPass(): void
    {
        $password = $this->createPassword(name: 'password');
        $confirmation = $this->createConfirmation(password: $password);
        $input = FormInput::fromArray(data: ['password' => 'Secret 1', 'confirm' => 'Secret 1']);

        $password->validate(input: $input);

        $this->assertTrue($confirmation->validate(input: $input));
    }

    public function testDifferentValuesAddTheErrorMessage(): void
    {
        $password = $this->createPassword(name: 'password');
        $confirmation = $this->createConfirmation(password: $password);
        $input = FormInput::fromArray(data: ['password' => 'Secret 1', 'confirm' => 'Secret 2']);

        $password->validate(input: $input);

        $this->assertFalse($confirmation->validate(input: $input));
        $this->assertSame('Different', $confirmation->errorCollection->getFirstError()->render());
        $this->assertFalse($password->hasErrors(withChildElements: true));
    }

    public function testValuesAreComparedExactlyWithoutNormalizing(): void
    {
        $other = $this->createPassword(name: 'other');
        $other->validate(input: FormInput::fromArray(data: ['other' => 'abc']));
        $rule = new EqualsFieldRule(otherField: $other, errorMessage: HtmlText::fromHtml(html: 'Different'));

        $this->assertTrue($rule->validate(value: 'abc'));
        $this->assertFalse($rule->validate(value: 'ABC'));
        $this->assertFalse($rule->validate(value: 'abc '));
    }

    public function testTheOtherFieldCanBeAnyStringField(): void
    {
        $email = new TextField(name: 'email', label: HtmlText::fromHtml(html: 'Email'), value: 'ann@example.com');
        $repeat = new TextField(name: 'repeat', label: HtmlText::fromHtml(html: 'Repeat'));
        $repeat->addRule(
            formRule: new EqualsFieldRule(otherField: $email, errorMessage: HtmlText::fromHtml(html: 'No')),
        );

        $this->assertTrue($repeat->validate(input: FormInput::fromArray(data: ['repeat' => 'ann@example.com'])));
    }

    public function testEmptyConfirmationIsLeftToTheRequiredRule(): void
    {
        $password = $this->createPassword(name: 'password');
        $confirmation = $this->createConfirmation(password: $password);
        $input = FormInput::fromArray(data: ['password' => 'Secret 1', 'confirm' => '']);

        $password->validate(input: $input);

        $this->assertFalse($confirmation->validate(input: $input));
        $this->assertSame(1, $confirmation->errorCollection->count());
        $this->assertSame('Required', $confirmation->errorCollection->getFirstError()->render());
    }
}
