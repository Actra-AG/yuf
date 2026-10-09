# Phone numbers

`PhoneNumber::createFromString(input:, defaultCountryCode:)` parses a number in national (`044 123 45 67`, with the
region, e.g. `'CH'`) or international notation (`+41 44 123 45 67`, with an extension) and throws a
`PhoneParseException` if its length is not possible. The metadata is a port of
[libphonenumber](https://github.com/google/libphonenumber) (Apache 2.0).

```php
$phoneNumber = PhoneNumber::createFromString(input: '044 123 45 67', defaultCountryCode: 'CH');

PhoneRenderer::renderE164Format(phoneNumber: $phoneNumber);          // +41441234567 (no extension)
PhoneRenderer::renderInternationalFormat(phoneNumber: $phoneNumber); // +41 44 123 45 67
PhoneRenderer::renderNationalFormat(phoneNumber: $phoneNumber);      // 044 123 45 67
PhoneRenderer::renderInternalFormat(phoneNumber: $phoneNumber);      // +41.441234567 (for storing)
```

The national format has the national prefix as the country writes it (`030 123456` in Germany, `(650) 253-0000` in the
US). International and national format end with the extension (` ext. 12`).

## Validity and type

A possible number has a length that exists in its country; a valid number also matches the numbers of the country.

- `isValid()` tells whether it is valid.
- `getType()` returns the `PhoneNumberTypeEnum` (`FIXED_LINE`, `MOBILE`, `TOLL_FREE`, `PREMIUM_RATE`, `SHARED_COST`,
  `VOIP`, `PERSONAL_NUMBER`, `PAGER`, `UAN`, `VOICEMAIL`) or `null` for a number that is not valid.
  `FIXED_LINE_OR_MOBILE` is returned where both cannot be told apart (e.g. in the US).
- `isValidForType(numberType:)` accepts such a number as `FIXED_LINE` and as `MOBILE`.

## Form field

`PhoneNumberField` accepts valid numbers only, otherwise it adds `invalidErrorMessage:`. With `allowedNumberTypes:` the
number also has to be of one of the types, otherwise the field adds `numberTypeErrorMessage:` (default:
`invalidErrorMessage:`):

```php
new PhoneNumberField(
    name: 'mobile',
    label: HtmlText::fromText(text: 'Mobile'),
    value: null,
    invalidErrorMessage: HtmlText::fromText(text: 'Invalid number'),
    allowedNumberTypes: [PhoneNumberTypeEnum::MOBILE],
    numberTypeErrorMessage: HtmlText::fromText(text: 'Please enter a mobile number'),
);
```
