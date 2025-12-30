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

// Convert back to JSON
$json = $data->toJson();
$prettyJson = $data->toPrettyJson();
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

// ... same as facade
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

### Normalizer
- `Normalizer::normalize(mixed $value): NormalizedData|mixed`
- `Normalizer::normalizeArray(array|Collection $input): array`
- `Normalizer::normalizeCollection(Collection $input): Collection`

### NormalizedData
- `$data->toArray(): array` - Recursively convert to plain array.
- `$data->toObject(): object` - Recursively convert to nested `stdClass` objects.
- `$data->toJson(int $options = 0): string` - Convert to JSON string.
- `$data->toPrettyJson(): string` - Convert to pretty-printed JSON string.
- `$data->all(): array` - Get the underlying items (one level).
- `$data->map(callable $callback): NormalizedData` - Recursively map over the data.
- `$data->pluck(string $key): NormalizedData` - Get the values of a given key.
- `$data->unique(string|callable|null $key = null): NormalizedData` - Return only unique items.
- `$data->sum(string|callable|null $key = null): mixed` - Sum the values of a given key.
- `$data->avg(string|callable|null $key = null): mixed` - Get the average value of a given key.
- `$data->max(string|callable|null $key = null): mixed` - Get the max value of a given key.
- `$data->min(string|callable|null $key = null): mixed` - Get the min value of a given key.
- `$data->count(?string $key = null, mixed $operator = null, mixed $value = null): int` - Number of items in the top level or matching criteria.
- `$data->when($value, callable $callback, ?callable $default = null): mixed` - Apply callback if value is true.
- `$data->unless($value, callable $callback, ?callable $default = null): mixed` - Apply callback if value is false.
- `$data->filter(?callable $callback = null): static` - Filter items by callback.
- `$data->reject($callback = true): static` - Create a new collection by filtering items using the given callback.
- `$data->where(string $key, mixed $operator = null, mixed $value = null): static` - Filter items by key/value pair.
- `$data->first(?callable $callback = null, mixed $default = null): mixed` - Get first item passing truth test.
- `$data->firstWhere(string $key, mixed $operator = null, mixed $value = null): mixed` - Get first item matching criteria.
- `$data->last(?callable $callback = null, mixed $default = null): mixed` - Get the last item from the collection.
- `$data->each(callable $callback): self` - Run an associative iteration over each of the items.
- `$data->every($key, $operator = null, $value = null): bool` - Determine if all items pass the given test.
- `$data->contains($key, $operator = null, $value = null): bool` - Determine if an item exists in the collection.
- `$data->except($keys): static` - Create a new collection by excluding the given keys.
- `$data->only($keys): static` - Create a new collection containing only the specified keys.
- `$data->flatMap(callable $callback): static` - Map a collection and flatten the result by a single level.
- `$data->collapse(): static` - Collapse the collection of items into a single array.
- `$data->forget($keys): self` - Remove an item from the collection by key.
- `$data->groupBy($groupBy, bool $preserveKeys = false): static` - Group an associative array by a field or using a callback.
- `$data->isEmpty(): bool` - Determine if the collection is empty.
- `$data->isNotEmpty(): bool` - Determine if the collection is not empty.
- `$data->keyBy($keyBy): static` - Key an associative array by a field or using a callback.
- `$data->keys(): static` - Get the keys of the collection items.
- `$data->merge($items): static` - Merge the collection with the given items.
- `$data->reduce(callable $callback, mixed $initial = null): mixed` - Reduce the collection to a single value.
- `$data->sort(?callable $callback = null): static` - Sort through each item with a callback.
- `$data->sortBy($callback, int $options = SORT_REGULAR, bool $descending = false): static` - Sort the collection using the given callback.
- `$data->sortByDesc($callback, int $options = SORT_REGULAR): static` - Sort the collection in descending order using the given callback.
- `$data->tap(callable $callback): self` - Pass the collection to the given callback and then return it.
- `$data->values(): static` - Reset the keys on the underlying array.

## Author

- Ahmed Elrayes <ahmedwaill63@gmail.com>

## License

MIT
