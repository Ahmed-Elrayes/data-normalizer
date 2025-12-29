<?php

namespace Elrayes\Normalizer\Tests;

use Elrayes\Normalizer\Support\NormalizedData;
use PHPUnit\Framework\TestCase;

class NormalizedDataAggregateMethodsTest extends TestCase
{
    public function test_sum_method()
    {
        $data = new NormalizedData([10, 20, 30]);
        $this->assertEquals(60, $data->sum());

        $data = new NormalizedData([
            ['price' => 10],
            ['price' => 20],
            ['price' => 30],
        ]);
        $this->assertEquals(60, $data->sum('price'));
        
        $data = new NormalizedData([
            ['items' => ['price' => 10]],
            ['items' => ['price' => 20]],
            ['items' => ['price' => 30]],
        ]);
        $this->assertEquals(60, $data->sum('items.price'));
    }

    public function test_max_method()
    {
        $data = new NormalizedData([10, 30, 20]);
        $this->assertEquals(30, $data->max());

        $data = new NormalizedData([
            ['price' => 10],
            ['price' => 30],
            ['price' => 20],
        ]);
        $this->assertEquals(30, $data->max('price'));
    }

    public function test_min_method()
    {
        $data = new NormalizedData([20, 10, 30]);
        $this->assertEquals(10, $data->min());

        $data = new NormalizedData([
            ['price' => 20],
            ['price' => 10],
            ['price' => 30],
        ]);
        $this->assertEquals(10, $data->min('price'));
    }

    public function test_avg_method()
    {
        $data = new NormalizedData([10, 20, 30]);
        $this->assertEquals(20, $data->avg());
        $this->assertEquals(20, $data->average());

        $data = new NormalizedData([
            ['price' => 10],
            ['price' => 20],
            ['price' => 30],
        ]);
        $this->assertEquals(20, $data->avg('price'));
    }
}
