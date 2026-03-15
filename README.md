# yuf - A Smart, Fast, and Lightweight PHP Framework

**yuf** (pronounced "[jʌf]" or "[jʊf]") is a smart, fast, and lightweight PHP framework designed with a focus on simplicity and performance. It has zero external dependencies, other than the `actra/autoloader` library which is required for all setups.

## Key Features

- **Extremely Lightweight**: Minimal overhead and fast execution.
- **Zero Dependencies**: Core framework functions without heavy external libraries.
- **Composer Ready**: Easy installation via Packagist.
- **Standalone Support**: Works perfectly without Composer.
- **Forced Autoloading**: Always uses the specialized `actra/autoloader` for maximum performance and control.
- **Built-in Security**: Includes features like CSP (Content Security Policy) nonce support.

## Requirements

- PHP 8.5 or higher
- Common PHP extensions: `mbstring`, `openssl`, `pdo`, `intl`, `bcmath`, `simplexml`, `dom`, `iconv`, `curl`

### Installation

Install `yuf` and `actra/autoloader` via Composer or download them manually. Note that `yuf` always requires `actra/autoloader` to be manually initialized.

### Via Composer (Recommended)

```bash
composer require actra/yuf
```

### Manual Installation

1. Download the source code from [GitHub](https://github.com/Actra-AG/yuf).
2. Download `actra/autoloader` (https://github.com/Actra-AG/autoloader) and place it in your project.
3. Reference the `Autoloader.php` when initializing the `Core` class.

## Quick Start

1. Create a `.env.php` file based on `.env.example.php`.
2. Create an `index.php` in your document root based on `index.example.php`.
3. Initialize the Framework Core and provide the path to `Autoloader.php` if not using the default.

## Documentation

For more detailed examples, please refer to:
- `.env.example.php`: Configuration examples.
- `index.example.php`: Full usage example with manual autoloader initialization.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

---
© 2026 [Actra AG](https://www.actra.ch)