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
     */
    public function __construct(array|object|null $items = null)
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
        $this->items = $this->wrapNested($items ?? []);
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

        return $this->wrap($current);
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
        $value = $this->get((string)$offset);
        return $this->toArrayValue($value);
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
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }
}
