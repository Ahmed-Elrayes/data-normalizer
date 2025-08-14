<?php

namespace Elrayes\Normalizer\Tests;

// A tiny config store and a global helper to mimic Laravel's config() used by the package.
// This avoids requiring the whole framework just to run unit tests.
class TestConfigStore
{
    private static array $items = [];

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            self::$items[$key] = $value;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$items[$key] ?? $default;
    }
}

