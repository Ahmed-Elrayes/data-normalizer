<?php

namespace Elrayes\Normalizer\Tests;

use Elrayes\Normalizer\Support\NormalizedData;
use PHPUnit\Framework\TestCase;

class NormalizedDataNewMethodsTest extends TestCase
{
    public function test_when_method()
    {
        $data = new NormalizedData([1, 2, 3]);

        $data->when(true, function ($data) {
            return $data->map(fn($item) => $item * 2);
        });

        $this->assertEquals([2, 4, 6], $data->when(true, function ($data) {
            return $data->map(fn($item) => $item * 2);
        })->all());

        $this->assertEquals([1, 2, 3], $data->when(false, function ($data) {
            return $data->map(fn($item) => $item * 2);
        })->all());
    }

    public function test_filter_method()
    {
        $data = new NormalizedData([1, 2, 3, 4, 5]);

        $filtered = $data->filter(fn($item) => $item > 3);

        $this->assertEquals([3 => 4, 4 => 5], $filtered->all());
    }

    public function test_where_method()
    {
        $data = new NormalizedData([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie'],
        ]);

        $this->assertEquals([1 => ['id' => 2, 'name' => 'Bob']], $data->where('name', 'Bob')->toArray());
        $this->assertEquals([0 => ['id' => 1, 'name' => 'Alice']], $data->where('id', '<', 2)->toArray());
    }

    public function test_first_method()
    {
        $data = new NormalizedData([1, 2, 3]);

        $this->assertEquals(1, $data->first());
        $this->assertEquals(2, $data->first(fn($item) => $item > 1));
        $this->assertEquals('default', $data->first(fn($item) => $item > 5, 'default'));
    }

    public function test_first_where_method()
    {
        $data = new NormalizedData([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $result = $data->firstWhere('name', 'Bob');
        $this->assertEquals(['id' => 2, 'name' => 'Bob'], $result instanceof NormalizedData ? $result->toArray() : $result);

        $result = $data->firstWhere('id', '<', 2);
        $this->assertEquals(['id' => 1, 'name' => 'Alice'], $result instanceof NormalizedData ? $result->toArray() : $result);
    }

    public function test_count_method_with_parameters()
    {
        $data = new NormalizedData([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Alice'],
        ]);

        $this->assertEquals(3, $data->count());
        $this->assertEquals(2, $data->count('name', 'Alice'));
        $this->assertEquals(1, $data->count('id', '>', 2));
    }
}
