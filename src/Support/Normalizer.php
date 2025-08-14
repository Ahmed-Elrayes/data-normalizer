<?php

namespace Elrayes\Normalizer\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;
use stdClass;

/**
 * Data Normalizer service.
 *
 * Responsibilities:
 * - Recursively normalizes arrays, objects, and Illuminate Collections while preserving keys.
 * - Converts nulls, empty strings, and configurable N/A-like values to null (see config/normalizer.php).
 * - Wraps normalized arrays/collections/objects in NormalizedData for convenient access via
 *   array syntax, property access, and dot-notation (with escaped dots support).
 */
class Normalizer
{
    /**
     * Normalize a value recursively.
     *
     * - Arrays, objects and Collections are normalized recursively while preserving keys
     * - Nulls, empty strings, and configured "N/A"-like variants are converted to null
     * - Arrays/Collections/objects are wrapped in NormalizedData for flexible access
     *
     * @param mixed $value Any value to normalize.
     * @return NormalizedData|mixed NormalizedData for arrays/objects/collections; scalar/null otherwise.
     */
    public function normalize(mixed $value)
    {
        if ($value instanceof Collection) {
            $array = $this->normalizeArray($value);
            return new NormalizedData($array);
        }

        if (is_array($value)) {
            $array = $this->normalizeArray($value);
            return new NormalizedData($array);
        }

        if (is_object($value)) {
            $array = $this->normalizeObject($value);
            return new NormalizedData($array);
        }

        return $this->normalizeScalar($value);
    }

    /**
     * Normalize an array (recursively) preserving keys.
     *
     * @param array<string|int, mixed>|Collection $input
     * @return array<string|int, mixed>
     */
    public function normalizeArray(array|Collection $input): array
    {
        if ($input instanceof Collection) {
            $input = $input->all();
        }

        $result = [];
        foreach ($input as $key => $val) {
            $result[$key] = $this->normalize($val);
        }

        return $result;
    }

    /**
     * Normalize an object (recursively) into an associative array.
     *
     * Supports stdClass and generic objects. If the object implements Arrayable or JsonSerializable,
     * those conversions are respected. Public properties are used otherwise.
     *
     * @param object $input The object to normalize.
     * @return array<string|int, mixed>
     */
    public function normalizeObject(object $input): array
    {
        if ($input instanceof Arrayable) {
            $input = $input->toArray();
        } elseif ($input instanceof JsonSerializable) {
            $input = $input->jsonSerialize();
        } elseif ($input instanceof stdClass) {
            $input = (array) $input;
        } else {
            $input = get_object_vars($input);
        }

        $result = [];
        foreach ($input as $key => $val) {
            $result[$key] = $this->normalize($val);
        }
        return $result;
    }

    /**
     * Normalize a collection (recursively) preserving keys.
     *
     * @param Collection $input
     * @return Collection
     */
    public function normalizeCollection(Collection $input): Collection
    {
        return $input->map(function ($val) {
            return $this->normalize($val);
        });
    }

    /**
     * Normalize a single scalar value.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function normalizeScalar(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            $treatEmpty = (bool) config('normalizer.treat_empty_string_as_null', true);
            $treatWhitespaceAsEmpty = (bool) config('normalizer.treat_whitespace_as_empty', true);

            if ($treatWhitespaceAsEmpty && $treatEmpty && trim($value) === '') {
                return null;
            }
            if (!$treatWhitespaceAsEmpty && $treatEmpty && $value === '') {
                return null;
            }

            // Configurable N/A-like values
            $mode = strtolower((string) config('normalizer.na_match_mode', 'compressed'));
            $values = array_map(fn($v) => strtolower(trim((string) $v)), (array) config('normalizer.na_values', ['na','n/a','n a','n-a','n.a']));

            $candidate = strtolower($trimmed);
            if ($mode === 'compressed') {
                $candidate = preg_replace('/[^a-z0-9]/i', '', $candidate) ?? $candidate;
                $normalizedList = array_map(function ($v) {
                    return preg_replace('/[^a-z0-9]/i', '', $v) ?? $v;
                }, $values);
                if (in_array($candidate, $normalizedList, true)) {
                    return null;
                }
            } else { // exact
                if (in_array($candidate, $values, true)) {
                    return null;
                }
            }

            return $trimmed;
        }

        return $value;
    }
}
