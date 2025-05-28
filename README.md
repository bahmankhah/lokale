# Lokale

**Lokale** is a Laravel package designed to automatically extract and generate translation files by scanning your source code for translation keys. It also supports syncing translations between different locales.

## 🧩 Installation

Install the package using Composer:

```bash
composer require hsm/lokale
```

## ⚙️ Configuration

This package uses Laravel's package discovery. If for some reason it's not auto-discovered, register it manually:

```php
'providers' => [
    Hsm\Lokale\LokaleServiceProvider::class,
];
```

## 🚀 Usage

### 1. Generate Locale Files

You can generate translation files by scanning your source code:

```bash
php artisan locale:make
```

#### Options

| Option        | Description                                                                 |
|---------------|-----------------------------------------------------------------------------|
| `--locale`    | Target locale to generate files for (default: value of `app.locale`).       |
| `--src`       | Directory to scan for translation keys (default: `app/`).                   |
| `--default`   | Used for generating default translation placeholders (default: `default`).  |
| `--comment`   | Adds `@TODO` comments to missing translations with context information.     |
| `--output`    | Output directory for language files (default: `lang/`).                     |

#### Example

```bash
php artisan locale:make --locale=fr --src=app --comment
```

This will generate files like:

```
resources/lang/fr/messages.php
resources/lang/fr/auth.php
```

Each file will contain translation keys extracted from `__()`, `trans()`, or `trans_choice()` usage across your source code.

### 2. Sync Between Locales

You can synchronize translation files between two locales using:

```bash
php artisan locale:sync --from=en --to=fr
```

#### Options

| Option        | Description                                                                 |
|---------------|-----------------------------------------------------------------------------|
| `--from`      | Source locale. Required.                                                    |
| `--to`        | Target locale. Required.                                                    |
| `--output`    | Base directory for language files (default: `lang/`).                       |
| `--comment`   | Adds `@TODO` comments for untranslated keys in target files.                |

This is useful when adding new keys in one language and you want the same structure in another language.

## 🧠 How It Works

- Scans PHP and Blade files for translation functions (`__()`, `trans()`, `trans_choice()`).
- Extracts keys and organizes them into proper translation files.
- Adds `@TODO` comments for untranslated placeholders when `--comment` is enabled.
- Generates modern, readable PHP array syntax.

## 📂 Output Example

`resources/lang/fr/messages.php`

```php
<?php
/**
 * Generated with hsm/lokale
 */
return [
    'welcome' => 'Welcome',
    'greeting' => 'Greeting', // @TODO Add translation
];
```

## 👨‍💻 Author

- **Amirhesam Bahmankhah**  
  📧 bahmankhah1@gmail.com

## 📄 License

MIT © Amirhesam Bahmankhah