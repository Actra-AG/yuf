# yuf

**yuf** (pronounced "[jʌf]" or "[jʊf]") is a smart, fast and lightweight PHP framework. Its only dependency is
[actra/autoloader](https://github.com/Actra-AG/autoloader), which loads all classes of yuf.

- Routing, views, JSON endpoints and an immutable request object
- Template engine with escaping by default and own tags
- Typed form fields, rules, CSRF protection and checked file uploads
- Session object, login with session fixation protection, Argon2id passwords, IP whitelists
- PDO connection with typed rows and a safe query builder
- Mail via SMTP (incl. XOAUTH2) or the Microsoft Graph API, phone numbers based on libphonenumber
- CSP nonces, ships no JavaScript

## Requirements

- PHP 8.5 or higher
- Extensions: `mbstring`, `openssl`, `pdo`, `intl`, `bcmath`, `simplexml`, `dom`, `iconv`, `curl`, `libxml`, `ctype`,
  `fileinfo`

## Installation

Start a new project with the [yuf skeleton](https://github.com/Actra-AG/yuf-skeleton):

```bash
composer create-project actra/yuf-skeleton my-project
```

Or add yuf to an existing project:

```bash
composer require actra/yuf
```

Without Composer, download yuf and [actra/autoloader](https://github.com/Actra-AG/autoloader) and pass the path of
`Autoloader.php` to `Core::fromEnvironment(autoloaderPath: …)`.

## Usage

```php
require __DIR__ . '/../vendor/actra/yuf/src/Core.php';
$core = Core::fromEnvironment(envFilePath: __DIR__ . '/../.env.php', copyrightYear: 2026);
$core->prepareHttpResponse(routeCollection: $routes)->sendAndExit();
```

See [.env.example.php](.env.example.php), [index.example.php](index.example.php) and the documentation:

- [Setup](docs/setup.md): entry point, environment settings, production, error log
- [Views and requests](docs/views.md): views, request, path variables, JSON endpoints
- [Session and login](docs/session-and-login.md): session, login, passwords, secret tokens
- [Templates](docs/templates.md): syntax, escaping, snippets and tables, own tags
- [Forms](docs/forms.md): typed values, rules, uploads, extension points
- [Database](docs/database.md): connection, typed rows, query builder, boolean search
- [Mail](docs/mail.md): SMTP and Microsoft 365
- [Phone numbers](docs/phone-numbers.md): parsing, formats, validation
- [Testing](docs/testing.md): PHPStan and PHPUnit in projects using yuf, test objects, clock

## Upgrading

Read [UPGRADE.md](UPGRADE.md) before `composer update`: minor versions may contain breaking changes (marked ⚠️).
Libraries that use yuf require it with `~4.67.0` (minor version locked).

## Contributing

Follow the [Actra coding standard](https://github.com/Actra-AG/coding-standard) and [AGENTS.md](AGENTS.md).
`composer check` (code style, PHPStan, tests) must be green; with [DDEV](https://ddev.com) run `ddev start` and
`ddev composer check`. The example app in `example/` then runs at https://yuf.ddev.site/.

## License

© 2026 [Actra AG](https://www.actra.ch). yuf is licensed under `MIT AND LGPL-2.1-only AND Apache-2.0`; the
`@license` tag in the header of each file says which applies:

- MIT, see [LICENSE](LICENSE): everything not listed below.
- LGPL-2.1-only, see [src/mailer/LICENSE](src/mailer/LICENSE): the classes in `src/mailer/` derived from
  [PHPMailer](https://github.com/PHPMailer/PHPMailer) (they say so in their header); the original copyright lines are
  kept.
- Apache-2.0, see [src/phone/LICENSE](src/phone/LICENSE) and [src/phone/NOTICE](src/phone/NOTICE): `src/phone/`,
  adapted from [libphonenumber-for-php](https://github.com/giggsey/libphonenumber-for-php).

Projects under any license, including proprietary ones, may use yuf. When you distribute yuf (or software containing
it), keep these license files and headers; the LGPL parts must stay replaceable and their source available, which
the PHP source of yuf in `vendor/` already ensures. Changes to the LGPL parts stay under the LGPL.
