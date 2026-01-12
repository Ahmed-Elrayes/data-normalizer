<?php

namespace Elrayes\Normalizer\Support;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use IteratorAggregate;
use JsonSerializable;
use stdClass;
use Traversable;

/**
 * NormalizedData
 *
 * A lightweight wrapper around normalized associative arrays that provides:
 * - Object-style access ($data->key)
 * - Array-style access ($data['key'])
 * - Dot-notation retrieval for nested values ($data['a.b.c'])
 * - Exact-key precedence when keys contain dots (e.g., $data['date.upload'])
 * - Escaping dots using a backslash for literal matches (e.g., $data['date\.upload'])
 *
 * This class is typically returned by the Normalizer service when normalizing
 * arrays, objects, or collections, enabling flexible and safe access patterns.
 */
class NormalizedData implements Arrayable, ArrayAccess, IteratorAggregate, JsonSerializable, Countable
{
    /**
     * @var array<string|int, mixed> Internal items map
     */
    protected array $items = [];

    /**
     * Create a new NormalizedData wrapper.
     *
     * @param array<string|int, mixed>|Collection|object|null $items Initial items or a collection/object convertible to an array
     * @param bool $wrap
     */
    public function __construct(array|object|null $items = null, bool $wrap = true)
    {
        if ($items instanceof Collection) {
            $items = $items->all();
        } elseif (is_object($items)) {
            if ($items instanceof Arrayable) {
                $items = $items->toArray();
            } elseif ($items instanceof JsonSerializable) {
                $items = $items->jsonSerialize();
            } elseif ($items instanceof stdClass) {
                $items = (array)$items;
            } else {
                $items = get_object_vars($items);
            }
        }

        $items = $items ?? [];

        $this->items = $wrap ? $this->wrapNested($items) : $items;
    }

    /**
     * Recursively wrap nested arrays/collections/objects into NormalizedData.
     *
     * @param mixed $value A nested value to wrap when appropriate
     * @return mixed Either a NormalizedData instance or the original scalar value
     */
    protected function wrap(mixed $value): mixed
    {
        if ($value instanceof self) {
            return $value;
        }
        if ($value instanceof Collection) {
            $value = $value->all();
        }
        if (is_object($value)) {
            if ($value instanceof Arrayable) {
                $value = $value->toArray();
            } elseif ($value instanceof JsonSerializable) {
                $value = $value->jsonSerialize();
            } elseif ($value instanceof stdClass) {
                $value = (array)$value;
            } else {
                $value = get_object_vars($value);
            }
        }
        if (is_array($value)) {
            return new self($value);
        }

        return $value;
    }

    /**
     * Wrap all nested items.
     *
     * @param array<string|int, mixed> $items
     * @return array<string|int, mixed>
     */
    protected function wrapNested(array $items): array
    {
        foreach ($items as $k => $v) {
            $items[$k] = $this->wrap($v);
        }
        return $items;
    }

    /**
     * Get an item using exact key or dot-notation. Exact key takes precedence.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // If exact key exists, return it (supports keys containing '.')
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        // Support escaping dots with a backslash: split on unescaped dots
        $segments = $this->splitKey($key);
        if (count($segments) === 1) {
            // Support escaped single-segment keys (e.g., 'date\.upload')
            $single = $this->unescape($segments[0]);
            return $this->items[$single] ?? $default;
        }

        $current = $this;
        foreach ($segments as $segment) {
            $segment = $this->unescape($segment);

            if ($current instanceof self) {
                if (!array_key_exists($segment, $current->items)) {
                    return $default;
                }
                $current = $current->items[$segment];
            } elseif (is_array($current)) {
                if (!array_key_exists($segment, $current)) {
                    return $default;
                }
                $current = $current[$segment];
            } elseif ($current instanceof Collection) {
                if (!$current->has([$segment])) {
                    return $default;
                }
                $current = $current->get($segment);
            } else {
                return $default;
            }
        }

        return $current;
    }

    /**
     * Split a key by unescaped dots.
     * Example: 'a.b', 'a\\.b.c' => ['a.b', 'c'] after unescape step.
     *
     * @return string[]
     */
    protected function splitKey(string $key): array
    {
        // Quick path: no dot present
        if (!str_contains($key, '.')) {
            return [$key];
        }

        // Split on dots not preceded by a backslash
        return preg_split('/(?<!\\\\)\./', $key) ?: [$key];
    }

    /**
     * Unescape a key segment (e.g., transforms "\\." into ".").
     */
    protected function unescape(string $segment): string
    {
        // Convert '\\.' => '.' and '\\\\' => '\\'
        return str_replace(['\\.', '\\\\'], ['.', '\\'], $segment);
    }

    /**
     * Magic and interface implementations
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    /**
     * Explicit accessor: $instance->all()
     * @return array
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Map the items to a new NormalizedData instance.
     * Supports recursive mapping for nested NormalizedData instances.
     *
     * @param callable $callback
     * @return self
     */
    public function map(callable $callback): self
    {
        $items = [];

        foreach ($this->items as $key => $value) {
            $mappedValue = $callback($value, $key);

            if ($mappedValue instanceof self) {
                $mappedValue = $mappedValue->map($callback);
            }

            $items[$key] = $mappedValue;
        }

        return new self($items);
    }

    /**
     * Chunk the items into multiple, smaller collections of a given size.
     *
     * @param int $size
     * @param bool $preserveKeys
     * @return static
     */
    public function chunk(int $size, bool $preserveKeys = true): static
    {
        if ($size <= 0) {
            return new static([]);
        }

        $chunks = [];

        foreach (array_chunk($this->items, $size, $preserveKeys) as $chunk) {
            $chunks[] = new static($chunk);
        }

        return new static($chunks, false);
    }

    /**
     * Chunk the items into multiple, smaller collections by a given key.
     *
     * @param callable|string $callback
     * @return static
     */
    public function chunkByKey(callable|string $callback): static
    {
        $callback = $this->valueRetriever($callback);

        $chunks = [];

        if (empty($this->items)) {
            return new static($chunks, false);
        }

        $currentChunk = [];
        $lastKey = null;

        foreach ($this->items as $key => $item) {
            $currentKey = $callback($item, $key);

            if ($lastKey !== null && $currentKey !== $lastKey) {
                $chunks[] = new static($currentChunk);
                $currentChunk = [];
            }

            $currentChunk[$key] = $item;
            $lastKey = $currentKey;
        }

        $chunks[] = new static($currentChunk);

        return new static($chunks, false);
    }

    /**
     * Get the values of a given key.
     *
     * @param string $value
     * @return self
     */
    public function pluck(string $value): self
    {
        $results = [];

        foreach ($this->items as $item) {
            if ($item instanceof self) {
                $results[] = $item->get($value);
            } elseif (is_array($item)) {
                $results[] = (new self($item))->get($value);
            }
        }

        return new self($results);
    }

    /**
     * Return only unique items from the collection.
     *
     * @param string|callable|null $key
     * @return self
     */
    public function unique(string|callable|null $key = null): self
    {
        if (is_null($key)) {
            return new self(array_unique($this->items, SORT_REGULAR));
        }

        $callback = $this->valueRetriever($key);

        $exists = [];

        $results = [];

        foreach ($this->items as $index => $item) {
            $value = $callback($item);

            if (!in_array($value, $exists, true)) {
                $results[$index] = $item;

                $exists[] = $value;
            }
        }

        return new self($results);
    }

    /**
     * Get the average value of a given key.
     *
     * @param callable|string|null $callback
     * @return mixed
     */
    public function avg(callable|string|null $callback = null): mixed
    {
        $callback = $this->valueRetriever($callback);

        $items = array_map($callback, $this->items);

        $items = array_filter($items, function ($value) {
            return !is_null($value);
        });

        if (empty($items)) {
            return null;
        }

        return array_sum($items) / count($items);
    }

    /**
     * Alias for the "avg" method.
     *
     * @param callable|string|null $callback
     * @return mixed
     */
    public function average(callable|string|null $callback = null): mixed
    {
        return $this->avg($callback);
    }

    /**
     * Get the max value of a given key.
     *
     * @param callable|string|null $callback
     * @return mixed
     */
    public function max(callable|string|null $callback = null): mixed
    {
        $callback = $this->valueRetriever($callback);

        $items = array_map($callback, $this->items);

        $items = array_filter($items, function ($value) {
            return !is_null($value);
        });

        if (empty($items)) {
            return null;
        }

        return max($items);
    }

    /**
     * Get the min value of a given key.
     *
     * @param callable|string|null $callback
     * @return mixed
     */
    public function min(callable|string|null $callback = null): mixed
    {
        $callback = $this->valueRetriever($callback);

        $items = array_map($callback, $this->items);

        $items = array_filter($items, function ($value) {
            return !is_null($value);
        });

        if (empty($items)) {
            return null;
        }

        return min($items);
    }

    /**
     * Sum the values of a given key.
     *
     * @param callable|string|null $callback
     * @return mixed
     */
    public function sum(callable|string|null $callback = null): mixed
    {
        $callback = $this->valueRetriever($callback);

        return array_sum(array_map($callback, $this->items));
    }

    /**
     * Get a value retriever callback.
     *
     * @param callable|string|null $value
     * @return callable
     */
    protected function valueRetriever(callable|string|null $value): callable
    {
        if (is_callable($value)) {
            return $value;
        }

        return function ($item) use ($value) {
            if ($item instanceof self) {
                return $item->get($value);
            }

            if (is_array($item)) {
                return (new self($item))->get($value);
            }

            return $item;
        };
    }

    /**
     * Apply the callback if the given "value" is (or resolves to) true.
     *
     * @param mixed $value
     * @param callable|null $callback
     * @param callable|null $default
     * @return $this|mixed
     */
    public function when(mixed $value, callable $callback = null, callable $default = null): mixed
    {
        $value = $value instanceof \Closure ? $value($this) : $value;

        if ($value) {
            return $callback($this, $value) ?? $this;
        } elseif ($default) {
            return $default($this, $value) ?? $this;
        }

        return $this;
    }

    /**
     * Run a filter over each of the items.
     *
     * @param callable|null $callback
     * @return static
     */
    public function filter(callable $callback = null): static
    {
        if ($callback) {
            return new static(Arr::where($this->items, $callback));
        }

        return new static(array_filter($this->items));
    }

    /**
     * Filter items by a given key value pair.
     *
     * @param string $key
     * @param mixed $operator
     * @param mixed $value
     * @return static
     */
    public function where(string $key, mixed $operator = null, mixed $value = null): static
    {
        return $this->filter($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Get the first item from the collection passing the given truth test.
     *
     * @param callable|null $callback
     * @param mixed $default
     * @return mixed
     */
    public function first(callable $callback = null, mixed $default = null): mixed
    {
        return Arr::first($this->items, $callback, $default);
    }

    /**
     * Get the first item by a given key value pair.
     *
     * @param string $key
     * @param mixed $operator
     * @param mixed $value
     * @return mixed
     */
    public function firstWhere(string $key, mixed $operator = null, mixed $value = null): mixed
    {
        return $this->first($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Get the last item from the collection.
     *
     * @param callable|null $callback
     * @param mixed $default
     * @return mixed
     */
    public function last(callable $callback = null, mixed $default = null): mixed
    {
        return Arr::last($this->items, $callback, $default);
    }

    /**
     * Run an associative iteration over each of the items.
     *
     * @param callable $callback
     * @return $this
     */
    public function each(callable $callback): self
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Determine if all items in the collection pass the given test.
     *
     * @param string|callable $key
     * @param mixed $operator
     * @param mixed $value
     * @return bool
     */
    public function every(string|callable $key, mixed $operator = null, mixed $value = null): bool
    {
        if (func_num_args() === 1 && is_callable($key)) {
            $callback = $key;

            foreach ($this->items as $k => $v) {
                if (!$callback($v, $k)) {
                    return false;
                }
            }

            return true;
        }

        return $this->where(...func_get_args())->count() === $this->count();
    }

    /**
     * Determine if an item exists in the collection.
     *
     * @param mixed $key
     * @param mixed $operator
     * @param mixed $value
     * @return bool
     */
    public function contains(mixed $key, mixed $operator = null, mixed $value = null): bool
    {
        if (func_num_args() === 1 && is_callable($key)) {
            return !is_null($this->first($key));
        }

        if (func_num_args() <= 1) {
            return in_array($key, $this->items);
        }

        return !is_null($this->firstWhere(...func_get_args()));
    }

    /**
     * Create a new collection by excluding the given keys.
     *
     * @param mixed $keys
     * @return static
     */
    public function except(mixed $keys): static
    {
        return new static(Arr::except($this->items, is_array($keys) ? $keys : func_get_args()));
    }

    /**
     * Create a new collection containing only the items with the specified keys.
     *
     * @param mixed $keys
     * @return static
     */
    public function only(mixed $keys): static
    {
        if (is_null($keys)) {
            return new static($this->items);
        }

        return new static(Arr::only($this->items, is_array($keys) ? $keys : func_get_args()));
    }

    /**
     * Map a collection and flatten the result by a single level.
     *
     * @param callable $callback
     * @return static
     */
    public function flatMap(callable $callback): static
    {
        $results = [];

        foreach ($this->items as $key => $value) {
            $results[] = $callback($value, $key);
        }

        return (new static($results))->collapse();
    }

    /**
     * Collapse the collection of items into a single array.
     *
     * @return static
     */
    public function collapse(): static
    {
        $results = [];

        foreach ($this->items as $values) {
            if ($values instanceof self) {
                $values = $values->all();
            } elseif ($values instanceof Arrayable) {
                $values = $values->toArray();
            }

            if (!is_array($values)) {
                continue;
            }

            $results = array_merge($results, $values);
        }

        return new static($results);
    }

    /**
     * Remove an item from the collection by key.
     *
     * @param string|array $keys
     * @return $this
     */
    public function forget(string|array $keys): self
    {
        foreach ((array)$keys as $key) {
            $this->offsetUnset($key);
        }

        return $this;
    }

    /**
     * Group an associative array by a field or using a callback.
     *
     * @param callable|string $groupBy
     * @param bool $preserveKeys
     * @return static
     */
    public function groupBy(callable|string $groupBy, bool $preserveKeys = false): static
    {
        if (is_string($groupBy)) {
            $groupBy = $this->valueRetriever($groupBy);
        }

        $results = [];

        foreach ($this->items as $key => $value) {
            $groupKeys = $groupBy($value, $key);

            if (!is_array($groupKeys)) {
                $groupKeys = [$groupKeys];
            }

            foreach ($groupKeys as $groupKey) {
                $groupKey = is_bool($groupKey) ? (int)$groupKey : $groupKey;

                if (!array_key_exists($groupKey, $results)) {
                    $results[$groupKey] = [];
                }

                if ($preserveKeys) {
                    $results[$groupKey][$key] = $value;
                } else {
                    $results[$groupKey][] = $value;
                }
            }
        }

        return new static($results);
    }

    /**
     * Determine if the collection is empty or not.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Determine if the collection is not empty.
     *
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Key an associative array by a field or using a callback.
     *
     * @param callable|string $keyBy
     * @return static
     */
    public function keyBy(callable|string $keyBy): static
    {
        $keyBy = $this->valueRetriever($keyBy);

        $results = [];

        foreach ($this->items as $key => $item) {
            $resolvedKey = $keyBy($item, $key);

            if (is_object($resolvedKey) && method_exists($resolvedKey, '__toString')) {
                $resolvedKey = (string)$resolvedKey;
            }

            $results[$resolvedKey] = $item;
        }

        return new static($results);
    }

    /**
     * Get the keys of the collection items.
     *
     * @return static
     */
    public function keys(): static
    {
        return new static(array_keys($this->items));
    }

    /**
     * Merge the collection with the given items.
     *
     * @param mixed $items
     * @return static
     */
    public function merge(mixed $items): static
    {
        return new static(array_merge($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Results array of items from Collection or Arrayable.
     *
     * @param mixed $items
     * @return array
     */
    protected function getArrayableItems(mixed $items): array
    {
        if (is_array($items)) {
            return $items;
        } elseif ($items instanceof self) {
            return $items->all();
        } elseif ($items instanceof Arrayable) {
            return $items->toArray();
        } elseif ($items instanceof JsonSerializable) {
            return $items->jsonSerialize();
        } elseif ($items instanceof Traversable) {
            return iterator_to_array($items);
        }

        return (array)$items;
    }

    /**
     * Reduce the collection to a single value.
     *
     * @param callable $callback
     * @param mixed $initial
     * @return mixed
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Create a new collection by filtering items using the given callback.
     *
     * @param callable|mixed $callback
     * @return static
     */
    public function reject($callback = true): static
    {
        $useDefault = func_num_args() <= 1 && !is_callable($callback);

        if ($useDefault) {
            $value = func_num_args() === 1 ? $callback : true;

            return $this->filter(function ($item) use ($value) {
                return $item != $value;
            });
        }

        return $this->filter(function ($item, $key) use ($callback) {
            return !$callback($item, $key);
        });
    }

    /**
     * Sort through each item with a callback.
     *
     * @param callable|int|null $callback
     * @return static
     */
    public function sort(callable|int|null $callback = null): static
    {
        $items = $this->items;

        $callback && is_callable($callback)
            ? uasort($items, $callback)
            : asort($items, $callback ?? SORT_REGULAR);

        return new static($items);
    }

    /**
     * Sort the collection using the given callback.
     *
     * @param callable|string $callback
     * @param int $options
     * @param bool $descending
     * @return static
     */
    public function sortBy(callable|string $callback, int $options = SORT_REGULAR, bool $descending = false): static
    {
        $results = [];

        $callback = $this->valueRetriever($callback);

        // First we will loop through the items and get the value from the callback
        // for each of the items. We will then place these values in the results
        // array so we can sort it and use the keys to get the real items.
        foreach ($this->items as $key => $value) {
            $results[$key] = $callback($value, $key);
        }

        $descending ? arsort($results, $options)
            : asort($results, $options);

        // Once we have sorted all of the keys in the desired order, we can loop
        // through them and grab the corresponding item from the original
        // array and place it in the sorted items array for the results.
        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }

        return new static($results);
    }

    /**
     * Sort the collection in descending order using the given callback.
     *
     * @param callable|string $callback
     * @param int $options
     * @return static
     */
    public function sortByDesc(callable|string $callback, int $options = SORT_REGULAR): static
    {
        return $this->sortBy($callback, $options, true);
    }

    /**
     * Pass the collection to the given callback and then return it.
     *
     * @param callable $callback
     * @return $this
     */
    public function tap(callable $callback): self
    {
        $callback($this);

        return $this;
    }

    /**
     * Apply the callback if the given "value" is (or resolves to) false.
     *
     * @param mixed $value
     * @param callable $callback
     * @param callable|null $default
     * @return $this|mixed
     */
    public function unless(mixed $value, callable $callback, callable $default = null): mixed
    {
        return $this->when(!$value, $callback, $default);
    }

    /**
     * Reset the keys on the underlying array.
     *
     * @return static
     */
    public function values(): static
    {
        return new static(array_values($this->items));
    }

    /**
     * Get an operator checker callback.
     *
     * @param string $key
     * @param mixed $operator
     * @param mixed $value
     * @return \Closure
     */
    protected function operatorForWhere(string $key, mixed $operator = null, mixed $value = null): \Closure
    {
        if (func_num_args() === 1) {
            $value = true;

            $operator = '=';
        }

        if (func_num_args() === 2) {
            $value = $operator;

            $operator = '=';
        }

        return function ($item) use ($key, $operator, $value) {
            $retrieved = $this->valueRetriever($key)($item);

            $strings = array_filter([$retrieved, $value], function ($value) {
                return is_string($value) || (is_object($value) && method_exists($value, '__toString'));
            });

            if (count($strings) < 2 && count(array_filter([$retrieved, $value], 'is_object')) == 1) {
                return in_array($operator, ['!=', '<>', '!==']);
            }

            switch ($operator) {
                default:
                case '=':
                case '==':
                    return $retrieved == $value;
                case '!=':
                case '<>':
                    return $retrieved != $value;
                case '<':
                    return $retrieved < $value;
                case '>':
                    return $retrieved > $value;
                case '<=':
                    return $retrieved <= $value;
                case '>=':
                    return $retrieved >= $value;
                case '===':
                    return $retrieved === $value;
                case '!==':
                    return $retrieved !== $value;
            }
        };
    }

    /**
     * @return array[]
     */
    public function __debugInfo()
    {
        return [
            'all' => $this->all(),
        ];
    }

    public function __isset(string $name): bool
    {
        return $this->offsetExists($name);
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        if (!is_string($offset) && !is_int($offset)) return false;
        $offset = (string)$offset;

        if (array_key_exists($offset, $this->items)) {
            return true;
        }

        $segments = $this->splitKey($offset);
        $current = $this;
        foreach ($segments as $segment) {
            $segment = $this->unescape($segment);
            if ($current instanceof self) {
                if (!array_key_exists($segment, $current->items)) return false;
                $current = $current->items[$segment];
            } elseif (is_array($current)) {
                if (!array_key_exists($segment, $current)) return false;
                $current = $current[$segment];
            } elseif ($current instanceof Collection) {
                if (!$current->has([$segment])) return false;
                $current = $current->get($segment);
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (!is_string($offset) && !is_int($offset)) return null;
        return $this->get((string)$offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!is_string($offset) && !is_int($offset)) return;
        $this->items[(string)$offset] = $this->wrap($value);
    }

    /**
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        if (!is_string($offset) && !is_int($offset)) return;
        unset($this->items[(string)$offset]);
    }

    /**
     * Get an iterator for the items.
     *
     * @return ArrayIterator
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Convert this NormalizedData into a stdClass/array hybrid recursively:
     * - Associative arrays become stdClass
     * - List arrays remain arrays (with their items converted recursively)
     *
     * @return object|array<string|int, mixed>
     */
    public function toObject(): object|array
    {
        return $this->toObjectValue($this->items);
    }

    /**
     * Convert the wrapped structure into a plain array recursively.
     *
     * @return array<string|int, mixed>
     */
    public function toArray(): array
    {
        return Arr::map($this->items, function ($v) {
            if ($v instanceof self) return $v->toArray();
            if ($v instanceof Collection) return $v->toArray();
            return $v;
        });
    }

    /**
     * JsonSerializable implementation.
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param int $options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object to its pretty JSON representation.
     *
     * @return string
     */
    public function toPrettyJson(): string
    {
        return $this->toJson(JSON_PRETTY_PRINT);
    }

    /**
     * Convert any value to an array recursively, preserving lists vs associative arrays.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function toArrayValue(mixed $value): mixed
    {
        if ($value instanceof self) {
            return $value->toArray();
        }
        if ($value instanceof Collection) {
            return $value->map(fn($v) => $this->toArrayValue($v))->all();
        }
        if ($value instanceof Arrayable) {
            return $this->toArrayValue($value->toArray());
        }
        if ($value instanceof JsonSerializable) {
            return $this->toArrayValue($value->jsonSerialize());
        }
        if ($value instanceof stdClass) {
            return $this->toArrayValue((array)$value);
        }
        if (is_array($value)) {
            return array_map(fn($v) => $this->toArrayValue($v), $value);
        }
        if (is_object($value)) {
            return $this->toArrayValue(get_object_vars($value));
        }
        return $value;
    }

    /**
     * Convert any value to an object-like structure recursively:
     * - Associative arrays become stdClass
     * - List arrays remain arrays (with their items converted)
     *
     * @param mixed $value
     * @return mixed
     */
    protected function toObjectValue(mixed $value): mixed
    {
        if ($value instanceof self) {
            return $value->toObject();
        }
        if ($value instanceof Collection) {
            // Collections become arrays of objectified values
            return $value->map(fn($v) => $this->toObjectValue($v))->all();
        }
        if ($value instanceof Arrayable) {
            return $this->toObjectValue($value->toArray());
        }
        if ($value instanceof JsonSerializable) {
            return $this->toObjectValue($value->jsonSerialize());
        }
        if ($value instanceof stdClass) {
            // Normalize stdClass properties recursively
            $arr = (array)$value;
            return $this->arrayToObject($arr);
        }
        if (is_array($value)) {
            return $this->arrayToObject($value);
        }
        if (is_object($value)) {
            $arr = get_object_vars($value);
            return $this->arrayToObject($arr);
        }
        return $value;
    }

    /**
     * Turn an array into a stdClass if associative; otherwise return a list with items converted.
     *
     * @param array $arr
     * @return array|object
     */
    protected function arrayToObject(array $arr): array|object
    {
        if (function_exists('array_is_list') ? array_is_list($arr) : $this->isList($arr)) {
            return array_map(fn($v) => $this->toObjectValue($v), $arr);
        }
        $obj = new stdClass();
        foreach ($arr as $k => $v) {
            $obj->{$k} = $this->toObjectValue($v);
        }
        return $obj;
    }

    /**
     * Polyfill for array_is_list on older runtimes.
     */
    private function isList(array $array): bool
    {
        $i = 0;
        foreach ($array as $k => $_) {
            if ($k !== $i++) return false;
        }
        return true;
    }

    /**
     * Count the number of items in the collection.
     *
     * @param string|null $key
     * @param mixed $operator
     * @param mixed $value
     * @return int
     */
    public function count(string $key = null, mixed $operator = null, mixed $value = null): int
    {
        if (is_null($key)) {
            return count($this->items);
        }

        return $this->where(...func_get_args())->count();
    }
}
