<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);
/**
 * Derived work from PHPMailer, reduced to the code needed by this Framework.
 * For the original full library, please see:
 *
 * @see       https://github.com/PHPMailer/PHPMailer/ The PHPMailer GitHub project
 * @author    Marcus Bointon (Synchro/coolbru) <phpmailer@synchromedia.co.uk>
 * @author    Jim Jagielski (jimjag) <jimjag@gmail.com>
 * @author    Andy Prevost (codeworxtech) <codeworxtech@users.sourceforge.net>
 * @author    Brent R. Matzelle (original founder)
 * @author    Actra AG (for derived, reduced code)  - www.actra.ch
 * @copyright 2012 - 2020 Marcus Bointon
 * @copyright 2010 - 2012 Jim Jagielski
 * @copyright 2004 - 2009 Andy Prevost
 * @copyright 2022 Actra AG
 * @license   http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 * @note      This program is distributed in the hope that it will be useful - WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
 */

namespace actra\yuf\mailer;

/**
 * An address of a message with its kind and an optional display name.
 *
 * The address is trimmed and lower cased, the domain is punycode encoded. Line breaks and everything that is not a
 * valid address are rejected, so an address can neither start a new header nor add an SMTP command.
 */
final readonly class MailerAddress
{
    private string $punyEncodedDomain;
    private string $emailNamePart;
    private string $addressName;

    private function __construct(
        public MailerAddressKindEnum $mailerAddressKindEnum,
        string $inputEmail,
        string $inputName,
    ) {
        $inputEmail = mb_strtolower(string: trim(string: $inputEmail));
        $atPosition = strrpos(haystack: $inputEmail, needle: '@');
        if ($atPosition === false) {
            throw new MailerException(message: 'Missing @-sign in the ' . $mailerAddressKindEnum->value . ' address.');
        }

        $this->punyEncodedDomain = MailerAddress::punyEncodeDomain(
            domain: substr(string: $inputEmail, offset: $atPosition + 1),
            mailerAddressKindEnum: $mailerAddressKindEnum,
        );
        $this->emailNamePart = substr(string: $inputEmail, offset: 0, length: $atPosition);

        if (!MailerAddress::isValid(address: $this->getPunyEncodedEmail())) {
            throw new MailerException(message: 'Invalid ' . $mailerAddressKindEnum->value . ' address.');
        }

        // Strip breaks and trim
        $this->addressName = trim(string: str_replace(search: ["\r", "\n"], replace: '', subject: $inputName));
    }

    public static function createSenderAddress(string $inputEmail, string $inputName): MailerAddress
    {
        return new MailerAddress(
            mailerAddressKindEnum: MailerAddressKindEnum::KIND_SENDER,
            inputEmail: $inputEmail,
            inputName: $inputName,
        );
    }

    public static function createFromAddress(string $inputEmail, string $inputName): MailerAddress
    {
        return new MailerAddress(
            mailerAddressKindEnum: MailerAddressKindEnum::KIND_FROM,
            inputEmail: $inputEmail,
            inputName: $inputName,
        );
    }

    public static function createConfirmReadingToAddress(string $inputEmail, string $inputName): MailerAddress
    {
        return new MailerAddress(
            mailerAddressKindEnum: MailerAddressKindEnum::KIND_CONFIRM_READING_TO,
            inputEmail: $inputEmail,
            inputName: $inputName,
        );
    }

    public static function createToAddress(string $inputEmail, string $inputName): MailerAddress
    {
        return new MailerAddress(
            mailerAddressKindEnum: MailerAddressKindEnum::KIND_TO,
            inputEmail: $inputEmail,
            inputName: $inputName,
        );
    }

    public static function createCcAddress(string $inputEmail, string $inputName): MailerAddress
    {
        return new MailerAddress(
            mailerAddressKindEnum: MailerAddressKindEnum::KIND_CC,
            inputEmail: $inputEmail,
            inputName: $inputName,
        );
    }

    public static function createBccAddress(string $inputEmail, string $inputName): MailerAddress
    {
        return new MailerAddress(
            mailerAddressKindEnum: MailerAddressKindEnum::KIND_BCC,
            inputEmail: $inputEmail,
            inputName: $inputName,
        );
    }

    public static function createReplyToAddress(string $inputEmail, string $inputName): MailerAddress
    {
        return new MailerAddress(
            mailerAddressKindEnum: MailerAddressKindEnum::KIND_REPLY_TO,
            inputEmail: $inputEmail,
            inputName: $inputName,
        );
    }

    public function getPunyEncodedEmail(): string
    {
        return $this->emailNamePart . '@' . $this->punyEncodedDomain;
    }

    public function getName(): string
    {
        return $this->addressName;
    }

    public function getFormattedAddressForMailer(
        int $maxLineLength,
        MailerCharsetEnum $defaultCharSet,
    ): string {
        $preparedEmailAddress = MailerHeaderEncoder::secure(string: $this->getPunyEncodedEmail());
        if ($this->addressName === '') {
            return $preparedEmailAddress;
        }

        return implode(
            separator: ' ',
            array: [
                MailerHeaderEncoder::encodePhrase(
                    string: MailerHeaderEncoder::secure(string: $this->addressName),
                    maxLineLength: $maxLineLength,
                    defaultCharSet: $defaultCharSet,
                ),
                '<' . $preparedEmailAddress . '>',
            ],
        );
    }

    private static function isValid(string $address): bool
    {
        // Line breaks are valid in RFC 5322, but not in RFC 5321 (SMTP)
        if (strpbrk(string: $address, characters: MailerConstants::CRLF) !== false) {
            return false;
        }

        return filter_var(
            value: $address,
            filter: FILTER_VALIDATE_EMAIL,
            options: FILTER_FLAG_EMAIL_UNICODE | FILTER_NULL_ON_FAILURE,
        ) !== null;
    }

    private static function punyEncodeDomain(string $domain, MailerAddressKindEnum $mailerAddressKindEnum): string
    {
        if (!MailerContentEncoder::has8bitChars(text: $domain)) {
            return $domain;
        }
        $punyEncoded = idn_to_ascii(
            domain: $domain,
            flags: IDNA_DEFAULT | IDNA_USE_STD3_RULES | IDNA_CHECK_BIDI | IDNA_CHECK_CONTEXTJ
            | IDNA_NONTRANSITIONAL_TO_ASCII,
        );
        if ($punyEncoded === false) {
            throw new MailerException(
                message: 'Invalid domain in the ' . $mailerAddressKindEnum->value . ' address.',
            );
        }

        return $punyEncoded;
    }
}
