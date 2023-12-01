# A laravel package for syncing local dev databases with remote

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ronnorthrip/dbpull.svg?style=flat-square)](https://packagist.org/packages/ronnorthrip/dbpull)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/ronnorthrip/dbpull/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/ronnorthrip/dbpull/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/ronnorthrip/dbpull/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/ronnorthrip/dbpull/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/ronnorthrip/dbpull.svg?style=flat-square)](https://packagist.org/packages/ronnorthrip/dbpull)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Installation

You can install the package via composer:

```bash
composer require ronnorthrip/dbpull
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag=dbpull-config
```



## Usage

The db:pull command pulls the remote database and imports it into your local database. 
Currently, the command only works with MySQL databases and over passwordless SSH.
The database is pulled into a temporary file locally in database/pulls and state is persisted in .dbpull.json.
You should probalby add both of these to your .gitignore.

```bash
php artisan db:pull
```

## Config

### Environment Variables

The package is highly configurable, and we've set reasonable defaults where possible. 
You'll need to at least configure access to your remote database in your .env file.

```php
DBPULL_PRODUCTION_DB_DATABASE=
DBPULL_PRODUCTION_DB_USERNAME=
DBPULL_PRODUCTION_DB_PASSWORD=
DBPULL_PRODUCTION_SSH=
```

### Config File

This is the contents of the published config file:

```php
return [
];
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Spaitie

This package was templated from Spatie's package skeleton at https://github.com/spatie/package-skeleton-laravel

## Credits

- [Ron Northrip](https://github.com/ronnorthrip)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
