# Data Normalizer

Recursively normalize values and access them like arrays, objects, or using dot notation (with support for keys that contain dots).

- Converts null, empty strings and common "n/a" variants (e.g., `n/a`, `N A`, `n-a`, `n.a`) to `null`.
- Recursively normalizes arrays and Illuminate Collections while preserving keys.
- Wraps arrays/collections in a NormalizedData object that supports:
  - `$data->key` (object-style)
  - `$data['key']` (array-style)
  - `$data['a.b.c']` (dot notation)
  - Keys with dots using exact-key precedence or escaping: `$data['date.upload']` or `$data['date\.upload']`.

## Install

### Packagist (recommended)

```
composer require elrayes/data-normalizer
```

This package supports Laravel package auto-discovery, so the Service Provider and the `Normalizer` facade alias are registered automatically.

## Usage
### Facade
```php
use Elrayes\Normalizer\Facades\Normalizer;

$data = Normalizer::normalize([
    'item' => '  ',
    'date.upload' => '2024-01-01',
    'nested' => ['x' => 'n/a', 'y' => 'value'],
]);

// Object-style
$value = $data->item; // null

// Array-style
$upload = $data['date.upload']; // '2024-01-01' (exact key precedence)

// Dot-notation for nested
$y = $data['nested.y']; // 'value'

// Escaping dot when the key contains a dot
$upload2 = $data['date\.upload']; // '2024-01-01'
```
### Class
```php
use Elrayes\Normalizer\Support\Normalizer;

$normalizer = new Normalizer();
$data = $normalizer->normalize([
    'item' => '  ',
    'date.upload' => '2024-01-01',
    'nested' => ['x' => 'n/a', 'y' => 'value'],
]);

// Object-style
$value = $data->item; // null

// Array-style
$upload = $data['date.upload']; // '2024-01-01' (exact key precedence)

// Dot-notation for nested
$y = $data['nested.y']; // 'value'

// Escaping dot when the key contains a dot
$upload2 = $data['date\.upload']; // '2024-01-01'
```

## Configuration

Publish the config and customize behavior:

```
php artisan vendor:publish --tag=normalizer-config
```

The config/normalizer.php file supports:

- treat_empty_string_as_null: bool (default: true)
- treat_whitespace_as_empty: bool (default: true)
- na_match_mode: 'compressed' | 'exact' (default: 'compressed')
  - compressed: removes non-alphanumeric characters before comparing
  - exact: compares exact trimmed lowercase strings
- na_values: array of strings (default includes common N/A variants: ["na", "n/a", "n a", "n-a", "n.a", "none", "null", "-"])

## API

- Normalizer::normalize(mixed $value): NormalizedData|mixed
- Normalizer::normalizeArray(array|Collection $input): array
- Normalizer::normalizeCollection(Collection $input): Collection

## Author

- Ahmed Elrayes <ahmedwaill63@gmail.com>

## License

MIT
