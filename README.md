# Lokale

Lokale is a Laravel package designed to generate locale files automatically by scanning your application for translation keys.

## Installation

You can install the package via Composer:

```sh
composer require hsm/lokale
```

## Configuration

Once installed, the package should be automatically discovered by Laravel. However, if you need to register the service provider manually, add the following line to your `config/app.php` file:

```php
'providers' => [
    Hsm\Lokale\LokaleServiceProvider::class,
];
```

## Usage

You can generate locale files using the provided Artisan command:

```sh
php artisan locale:make --locale=en --src=app
```

### Command Options

- `--locale`: Specify the target locale (default: application locale from `config('app.locale')`).
- `--src`: Specify the source directory to scan for translation keys (default: `app/`).

## How It Works

1. The command scans the specified directory (`--src`) for translation keys.
2. Extracts all the translation keys used in the application.
3. Creates language files inside the `resources/lang/{locale}` directory.

## Example

Assuming you have the following translation keys in your Blade templates or controllers:

```php
__('messages.welcome')
__('auth.failed')
```

Running the command:

```sh
php artisan locale:make --locale=fr --src=app
```

Will generate the following files:

```
resources/lang/fr/messages.php
resources/lang/fr/auth.php
```

Each file will contain an associative array with extracted keys, ready for translation.

## Author

- **Amirhesam Bahmankhah** (bahmankhah1@gmail.com)

## License

This package is open-sourced under the MIT License.

