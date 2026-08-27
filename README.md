# Comfino Payment Gateway for WooCommerce

[![Tests](https://github.com/comfino/WooCommerce/workflows/Tests/badge.svg)](https://github.com/comfino/WooCommerce/actions)
[![PHP Version](https://img.shields.io/badge/php-7.1%20to%208.4-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-OSL--3.0-green.svg)](LICENSE)

> **Notice:** Version 4.3.1 is the **last release compatible with PHP 7.1**. The upcoming version 5.0.0 will require **PHP 8.1 or higher**, **WooCommerce 7.0.0 or higher**, and **WordPress 6.1 or higher**. Please plan your environment upgrade accordingly.

WooCommerce payment module for Comfino deferred payments gateway - installment payments, buy now pay later (BNPL) and corporate payments.

## Installation

### Polish

[Installation guide (Polish)](https://github.com/comfino/WooCommerce/blob/master/docs/comfino.pl.md)

### English

[Installation guide (English)](https://github.com/comfino/WooCommerce/blob/master/docs/comfino.en.md)

## Compatibility

### Current version (4.3.1 — last PHP 7.1 compatible release)

- **WooCommerce**: 3.0.0 or higher
- **WordPress**: 4.7 or higher
- **PHP**: 7.1 or higher
- **PHP extensions**: ctype, curl, json, zlib

### Upcoming version (5.0.0)

- **WooCommerce**: 7.0.0 or higher
- **WordPress**: 6.1 or higher
- **PHP**: 8.1 or higher
- **PHP extensions**: ctype, curl, json, sodium, zlib

## Development

### Requirements

- PHP 7.1 or higher
- WooCommerce 3.0.0 or higher
- PHP extensions: ctype, curl, json, zlib
- Docker and Docker Compose (for local development)

### Local development setup

```bash
# Start development environment.
docker-compose up -d

# Install dependencies.
./bin/composer install

# Run tests.
./bin/composer test

# Run tests with coverage.
XDEBUG_MODE=coverage ./bin/phpunit --coverage-html coverage
```

### Running tests

```bash
# Using Composer.
./bin/composer test

# Direct PHPUnit execution.
./bin/phpunit

# With specific test file.
./bin/phpunit tests/MainTest.php

# With coverage report.
XDEBUG_MODE=coverage ./bin/phpunit --coverage-html coverage
```

## Language files generation

Generate translation template files for internationalization:

### Using wp-cli

1. Install wp-cli on your development machine: https://github.com/wp-cli/wp-cli

2. Run on Docker:
   ```bash
   curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
   chmod +x wp-cli.phar
   ./wp-cli.phar --allow-root i18n make-pot wp-content/plugins/comfino-payment-gateway/
   ```

3. Open Poedit and edit the `.pot` file to generate `.mo` files

## Contributing

1. Fork the repository.
2. Create your feature branch (`git checkout -b feature/amazing-feature`).
3. Commit your changes (`git commit -m 'Add amazing feature'`).
4. Push to the branch (`git push origin feature/amazing-feature`).
5. Open a Pull Request.

All pull requests are currently tested against PHP 7.1-8.4 with both lowest and stable dependencies. Starting with version 5.0.0, the minimum supported PHP version will be 8.1.

## License

This project is licensed under the Open Software License 3.0 - see the LICENSE file for details.

## Support

- Documentation (Polish): [Comfino WooCommerce plugin documentation](https://comfino.pl/plugins/WooCommerce/pl)
- Documentation (English): [Comfino WooCommerce plugin documentation](https://comfino.pl/plugins/WooCommerce/en)
- Issues: [GitHub Issues](https://github.com/comfino/WooCommerce/issues)
- Website: https://comfino.pl
