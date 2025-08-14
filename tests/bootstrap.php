<?php

// Composer autoload
use Elrayes\Normalizer\Tests\TestConfigStore;

require_once __DIR__ . '/../vendor/autoload.php';

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return TestConfigStore::get($key, $default);
    }
}

// Set default config values for tests matching config/normalizer.php
TestConfigStore::set([
    'normalizer.treat_empty_string_as_null' => true,
    'normalizer.treat_whitespace_as_empty' => true,
    'normalizer.na_match_mode' => 'compressed',
    'normalizer.na_values' => [
        'na', 'n/a', 'n a', 'n-a', 'n.a', 'none', 'null', '-'
    ],
]);
