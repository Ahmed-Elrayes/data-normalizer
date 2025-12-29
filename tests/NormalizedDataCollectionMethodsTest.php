<?php

namespace Elrayes\Normalizer\Tests;

use Elrayes\Normalizer\Support\NormalizedData;
use PHPUnit\Framework\TestCase;

class NormalizedDataCollectionMethodsTest extends TestCase
{
    public function test_pluck_method()
    {
        $data = new NormalizedData([
            ['id' => 1, 'name' => 'Alice', 'profile' => ['username' => 'alice123']],
            ['id' => 2, 'name' => 'Bob', 'profile' => ['username' => 'bob456']],
            ['id' => 3, 'name' => 'Charlie', 'profile' => ['username' => 'charlie789']],
        ]);

        $this->assertEquals(['Alice', 'Bob', 'Charlie'], $data->pluck('name')->all());
        $this->assertEquals(['alice123', 'bob456', 'charlie789'], $data->pluck('profile.username')->all());
    }

    public function test_unique_method_without_parameters()
    {
        $data = new NormalizedData([1, 1, 2, 2, 3, 3]);
        $this->assertEquals([0 => 1, 2 => 2, 4 => 3], $data->unique()->all());
    }

    public function test_unique_method_with_key()
    {
        $data = new NormalizedData([
            ['id' => 1, 'role' => 'admin'],
            ['id' => 2, 'role' => 'user'],
            ['id' => 3, 'role' => 'admin'],
        ]);

        $unique = $data->unique('role');
        $this->assertCount(2, $unique);
        $this->assertEquals([0, 1], array_keys($unique->all()));
    }

    public function test_unique_method_with_callback()
    {
        $data = new NormalizedData([
            ['id' => 1, 'role' => 'admin'],
            ['id' => 2, 'role' => 'user'],
            ['id' => 3, 'role' => 'admin'],
        ]);

        $unique = $data->unique(function ($item) {
            return $item['role'];
        });

        $this->assertCount(2, $unique);
        $this->assertEquals([0, 1], array_keys($unique->all()));
    }

    public function test_unique_method_with_nested_key()
    {
        $data = new NormalizedData([
            ['id' => 1, 'profile' => ['username' => 'alice']],
            ['id' => 2, 'profile' => ['username' => 'bob']],
            ['id' => 3, 'profile' => ['username' => 'alice']],
        ]);

        $unique = $data->unique('profile.username');
        $this->assertCount(2, $unique);
        $this->assertEquals([0, 1], array_keys($unique->all()));
    }

    public function test_unique_method_checks_if_key_exists_before_dot_notation()
    {
        $data = new NormalizedData([
            ['profile.username' => 'direct1', 'profile' => ['username' => 'nested1']],
            ['profile.username' => 'direct1', 'profile' => ['username' => 'nested2']],
        ]);

        // It should prefer the direct key 'profile.username' if it exists
        $unique = $data->unique('profile.username');
        $this->assertCount(1, $unique);
    }
}
