# CAPTCHA

An optional CAPTCHA in the spam protection of a public form ([forms.md](forms.md#spam-protection)), for forms that
need more than honeypot, signed render time and rate limit. Provider: Cloudflare Turnstile (`TurnstileCaptcha`);
other providers implement `CaptchaProvider`.

## Setup

1. Create a widget in the Cloudflare dashboard (Turnstile) with the host names of the site. Keep the site key and the
   secret key in `.env.php` (never in Git).
2. Pass a `FormCaptcha` to the spam protection:

```php
$form->addSpamProtection(spamProtection: new SpamProtection(
    clock: $clock,
    secret: $environmentSettings->getString(key: 'spamProtectionSecret'),
    rateLimit: $formRateLimit,
    captcha: new FormCaptcha(
        provider: new TurnstileCaptcha(
            siteKey: $environmentSettings->getString(key: 'turnstileSiteKey'),
            secretKey: $environmentSettings->getString(key: 'turnstileSecretKey'),
            hostnames: ['www.example.com'], // null as explicit opt-out of the host name check
            action: 'contact', // optional: rendered as data-action and checked
        ),
        failMode: CaptchaFailModeEnum::CLOSED, // or OPEN, see below
        logger: $logger,
        cspNonce: $viewContext->content->cspNonce, // null without nonce in the Content-Security-Policy
    ),
    emailField: $emailField,
    rejectedEmailAddresses: [],
));
```

- The form renders the widget after the other spam fields, inside `<div class="form-captcha">`: the script
  `https://challenges.cloudflare.com/turnstile/v0/api.js` (with the nonce, `async defer`) and
  `<div class="cf-turnstile" data-sitekey="…" data-response-field-name="captcha">`. No inline scripts or styles; the
  name of the response field is `fieldName:` of `FormCaptcha` (default `captcha`). Use one form with CAPTCHA per page.
- `validate()` verifies the response last: only if the fields are valid and honeypot, render time and rate limit
  pass, so bots that fail the cheap rules cost no request to Cloudflare. An empty response fails without request.
- The widget needs JavaScript: without it, the response stays empty and every submit fails with `$captchaFailed`.
  Tell the visitors near the form, or keep the CAPTCHA off forms that must work without JavaScript.
- Errors of the form: `FormMessages::$captchaFailed` (invalid, expired or reused response) and `$captchaUnavailable`
  (en/de).
- Turnstile is asked with `POST https://challenges.cloudflare.com/turnstile/v0/siteverify` (secret, response,
  client IP address; 3 seconds to connect, 6 seconds in all). The response must have `success: true`, one of the
  `hostnames:` and the `action:`. A response is valid for 300 seconds and can be verified once.
- `failMode:` decides what happens when Cloudflare cannot verify (not reachable, `internal-error`, a wrong secret
  key): `CLOSED` blocks with `$captchaUnavailable`, `OPEN` lets the submit pass with the other rules only. The reason
  is logged with `logger:` either way, never the secret key or the response.
- Tests and development: the test keys of Cloudflare
  (https://developers.cloudflare.com/turnstile/troubleshooting/testing/) always pass or fail; their answer has another
  host name (e.g. `localhost`), so list it in `hostnames:` there. `TurnstileCaptcha` takes `siteverifyUrl:` for a
  local server.

## Content-Security-Policy

Turnstile loads its script from and renders an iframe of `https://challenges.cloudflare.com`. With the default
`CspPolicySettings` (`'strict-dynamic'` with the nonce) the nonce is enough for the script; allow the frame:

```php
$core->prepareHttpResponse(
    routeCollection: $routes,
    cspPolicySettings: new CspPolicySettings(
        scriptSrc: "'strict-dynamic' https://challenges.cloudflare.com", // the host for browsers without strict-dynamic
        frameSrc: 'https://challenges.cloudflare.com',
    ),
);
```

## Privacy

Turnstile sends data of the visitor (IP address, browser and device data) to Cloudflare, also when the form is only
displayed. Name Cloudflare Turnstile, its purpose (protection against spam) and the data in the privacy policy of the
site, and use it only on forms that need it.
