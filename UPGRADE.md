# Upgrade

Changes of yuf, newest first. ⚠️ marks breaking changes: read them before `composer update`. Older versions:
[v4.0.0–v4.49.0](docs/upgrade/v4.md), [v3](docs/upgrade/v3.md), [v2](docs/upgrade/v2.md), [v1](docs/upgrade/v1.md),
[v0](docs/upgrade/v0.md).

## v4.59.0 (2026-10-09)

### ⚠️ Generated content is not stored by browsers

HTML, JSON and text responses send `Cache-Control: private, no-store` and no ETag or `Last-Modified` (before:
`private, must-revalidate` with both). They are never answered with `304`; this fixes stale pages when the same URL was
requested twice within one second. A view that wants a cacheable response sets the headers itself.

### Other changes

- File responses: quoted ETag, `If-None-Match` with lists and weak comparison (takes precedence over
  `If-Modified-Since`), `Cache-Control` with `max-age`, new optional `isPublic:` and `isImmutable:` of
  `createResponseFromFilePath()`, no `Connection: Close` on `304` ([docs/views.md](docs/views.md)).
- `sendAndExit()` closes a started session before the content is sent and streams files without output buffers.
- The `clock:` argument of `createHtmlResponse()` and `createResponseFromString()` is not used any more.

## v4.58.0 (2026-10-09)

- `FileLogger` sends new issues with a mailer (`SmtpMailer`, `GraphMailer`) when it gets `mailer:` and
  `mailSenderAddress:`, with `mail()` as fallback if the mailer fails. Without them it mails with `mail()` as before
  ([docs/setup.md](docs/setup.md)).
- `ClassNameViewFactory` takes an optional `create:` closure to create the view with further dependencies
  ([docs/views.md](docs/views.md)).

## v4.57.6 (2026-10-09)

- The package no longer contains the empty plan directories in `docs/`. No code change.

## v4.57.5 (2026-10-09)

- The documentation moved from `README.md` to `docs/` and is part of the package now; upgrade notes up to v4.49.0 are
  in `docs/upgrade/`. No code change.

## v4.57.4 (2026-10-08)

- Documentation: PHPStan and PHPUnit setup in projects using yuf ([docs/testing.md](docs/testing.md)).

## v4.57.3 (2026-10-08)

- Documentation: lowercase class names of `ClassNameViewFactory` views are an allowed exception
  ([docs/views.md](docs/views.md)).

## v4.57.2 (2026-10-08)

- Documentation: error messages of forms use texts with named placeholders ([docs/forms.md](docs/forms.md)).

## v4.57.1 (2026-10-08)

- Documentation: rules for forms ([docs/forms.md](docs/forms.md)).

## v4.57.0 (2026-10-08)

### ⚠️ `acceptRedirectionResponseCode()` accepts 302, 307 and 308

Before, only 301 and 303 were accepted. Redirects are still not followed (the target is in the `Location` header).
Code that relied on 302, 307 or 308 being an error checks `$response->responseHttpCode` itself. Without
`acceptRedirectionResponseCode()` every status of 300 or more is an error; 300, 304, 305 and 306 are always errors.

### ⚠️ New `HttpStatusCodeEnum::HTTP_PERMANENT_REDIRECT`

A 308 answer has `CurlResponse::$responseHttpCode` `HTTP_PERMANENT_REDIRECT` (before: `HTTP_UNKNOWN`).

## v4.56.0 (2026-10-08)

- New `GraphMailer` and `MicrosoftClientCredentialsTokenProvider`: send mail through the Microsoft Graph API
  ([docs/mail.md](docs/mail.md)).

## v4.55.0 (2026-10-08)

### ⚠️ `SmtpMailer` chooses the authentication method from the `AUTH` line of the server

Before, `AUTH LOGIN` was always used. Now a server that announces `PLAIN` gets `AUTH PLAIN`, otherwise `LOGIN`; a
server that announces neither (e.g. only `CRAM-MD5`) aborts with a `MailerException`. Credentials with a NUL character
cannot be sent with `PLAIN`. To keep the old behaviour, pass `authMethod: SmtpAuthMethodEnum::LOGIN`.

### Other changes

- New optional arguments of `SmtpMailer`: `authMethod:` and `oAuthTokenProvider:` (`XOAUTH2`), interface
  `OAuthTokenProvider` ([docs/mail.md](docs/mail.md)).

## v4.54.0 (2026-10-08)

### ⚠️ `PhoneNumberField` accepts valid numbers only

Before, every number of a possible length was accepted (e.g. `044 668 18 00 / 12`). Now the number must be valid
(`PhoneNumber::isValid()`); an invalid number stays as typed. Check forms that accepted unusual numbers (extensions with
a slash, unassigned prefixes, test numbers). Stored numbers are not affected.

## v4.53.1 (2026-10-08)

- Phone numbers are parsed, validated and rendered like libphonenumber: leading zeros are kept in every country (e.g.
  Gabon `01441234`), the national format of Argentina is corrected. `PhoneMatcher::matchesCompletely()` (internal) is
  removed.

## v4.53.0 (2026-10-08)

- New `PhoneNumberTypeEnum`, `PhoneNumber::isValid()`, `getType()` and `isValidForType()`.
- New `PhoneRenderer::renderE164Format()` and `renderNationalFormat()`.
- New `allowedNumberTypes:` and `numberTypeErrorMessage:` of `PhoneNumberField`
  ([docs/phone-numbers.md](docs/phone-numbers.md)).
- MIME boundaries and message ids have 42 hexadecimal characters.

## v4.52.1 (2026-10-08)

- Code style only.

## v4.52.0 (2026-10-08)

### ⚠️ `IbanValidator` checks the length per country

An IBAN must have the length of its country (CH 21, DE 22, …, SWIFT IBAN registry). `IbanNumberField` rejects other
lengths now.

### ⚠️ `IpValidator::isInWhitelist()` takes IPv4-mapped IPv6 addresses as IPv4

`::ffff:192.0.2.1` matches the entry `192.0.2.0/24`, and the entry `::ffff:192.0.2.1` matches `192.0.2.1`. Check
whitelists with `::ffff:0:0/96`: it admits all IPv4 clients now.

### ⚠️ `CountryCodeEnum::AA` and `::UR` are removed

Neither is an ISO 3166-1 code. Replace stored values with the right code.

### ⚠️ Duplicate identifiers throw

`TableFilter::addPrimaryField()` / `addSecondaryField()` with an identifier already used and
`NavigationItemCollection::addItem()` with a `navKey` already used throw an `InvalidArgumentException` (before: the
second replaced the first).

### ⚠️ `DateFilterField` accepts date formats only

Only `Y-m-d`, `Y-n-j`, `d.m.Y`, `j.n.Y` (optionally with ` H:i` or ` H:i:s`) and the `renderFormat` of the field.
Before, everything `DateTimeImmutable` understood (`tomorrow`, `+1 day`, `01/03/2026`). An unreadable value in the
session is ignored instead of throwing.

## v4.51.0 (2026-10-08)

### ⚠️ `FormRenderer::prepare()` is replaced by `createHtmlTag()`

Renderers build a new tag on every call, so a component can be rendered more than once. `prepare()`,
`prepareHtmlTag()`, `getHtmlTag()` and `setHtmlTag()` are removed.

Before:

```php
public function prepare(): void { …; $this->setHtmlTag(htmlTag: $tag); }
$tag->addTag(htmlTag: $renderer->prepareHtmlTag());
```

After:

```php
public function createHtmlTag(): HtmlTag { …; return $tag; }
$tag->addTag(htmlTag: $renderer->createHtmlTag());
```

In an `InputFieldRenderer`: `$tag = parent::createHtmlTag(); $tag->addHtmlTagAttribute(…); return $tag;`.

## v4.50.0 (2026-10-08)

### ⚠️ `HtmlDataObject::$data` is replaced by `toTemplateData()`

`toTemplateData()` returns a new `stdClass` snapshot. Before: `$object->data->name`. After:
`$object->toTemplateData()->name`. Set values with `addHtml()` / `addText()` / `addHtmlDataObjectsArray()` instead of
writing to `$data`; in tests compare with `assertEquals`, not `assertSame`.

### ⚠️ Added data objects are copied

`addDataObject()` and `addHtmlDataObjectsArray()` store a copy of the child. Fill the child before adding it; later
changes of the child no longer change the parent.
