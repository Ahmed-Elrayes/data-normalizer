<?php

namespace Elrayes\Normalizer\Tests;

use Elrayes\Normalizer\Support\NormalizedData;
use PHPUnit\Framework\TestCase;

class NormalizedDataAdditionalMethodsTest extends TestCase
{
    public function test_last_method()
    {
        $data = new NormalizedData([1, 2, 3]);
        $this->assertEquals(3, $data->last());

        $data = new NormalizedData([1, 2, 3]);
        $this->assertEquals(2, $data->last(fn($v) => $v < 3));
    }

    public function test_each_method()
    {
        $data = new NormalizedData([1, 2, 3]);
        $result = [];
        $data->each(function ($item) use (&$result) {
            $result[] = $item * 2;
        });
        $this->assertEquals([2, 4, 6], $result);

        $result = [];
        $data->each(function ($item) use (&$result) {
            $result[] = $item;
            if ($item === 2) return false;
            return true;
        });
        $this->assertEquals([1, 2], $result);
    }

    public function test_every_method()
    {
        $data = new NormalizedData([1, 2, 3]);
        $this->assertTrue($data->every(fn($v) => $v > 0));
        $this->assertFalse($data->every(fn($v) => $v > 1));

        $data = new NormalizedData([
            ['id' => 1, 'active' => true],
            ['id' => 2, 'active' => true],
        ]);
        $this->assertTrue($data->every('active', true));
        $this->assertFalse($data->every('id', 1));
    }

    public function test_contains_method()
    {
        $data = new NormalizedData([1, 2, 3]);
        $this->assertTrue($data->contains(2));
        $this->assertFalse($data->contains(4));

        $this->assertTrue($data->contains(fn($v) => $v > 2));

        $data = new NormalizedData([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);
        $this->assertTrue($data->contains('name', 'Alice'));
        $this->assertFalse($data->contains('name', 'Charlie'));
    }

    public function test_except_and_only_methods()
    {
        $data = new NormalizedData(['a' => 1, 'b' => 2, 'c' => 3]);
        $this->assertEquals(['a' => 1, 'c' => 3], $data->except(['b'])->all());
        $this->assertEquals(['b' => 2], $data->only(['b'])->all());
    }

    public function test_flatMap_and_collapse_methods()
    {
        $data = new NormalizedData([
            ['name' => 'Alice', 'tags' => ['php', 'laravel']],
            ['name' => 'Bob', 'tags' => ['python', 'django']],
        ]);

        $tags = $data->flatMap(fn($item) => $item['tags']);
        $this->assertEquals(['php', 'laravel', 'python', 'django'], $tags->values()->all());

        $data = new NormalizedData([[1, 2], [3, 4]]);
        $this->assertEquals([1, 2, 3, 4], $data->collapse()->all());
    }

    public function test_forget_method()
    {
        $data = new NormalizedData(['a' => 1, 'b' => 2]);
        $data->forget('a');
        $this->assertEquals(['b' => 2], $data->all());
    }

    public function test_groupBy_method()
    {
        $data = new NormalizedData([
            ['account_id' => 1, 'value' => 10],
            ['account_id' => 2, 'value' => 20],
            ['account_id' => 1, 'value' => 30],
        ]);

        $grouped = $data->groupBy('account_id');
        $this->assertCount(2, $grouped);
        $this->assertCount(2, $grouped[1]);
        $this->assertCount(1, $grouped[2]);
    }

    public function test_isEmpty_and_isNotEmpty_methods()
    {
        $data = new NormalizedData([]);
        $this->assertTrue($data->isEmpty());
        $this->assertFalse($data->isNotEmpty());

        $data = new NormalizedData([1]);
        $this->assertFalse($data->isEmpty());
        $this->assertTrue($data->isNotEmpty());
    }

    public function test_keyBy_method()
    {
        $data = new NormalizedData([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $keyed = $data->keyBy('id');
        $this->assertEquals(['1', '2'], array_keys($keyed->all()));
        $this->assertEquals('Alice', $keyed[1]['name']);
    }

    public function test_keys_and_values_methods()
    {
        $data = new NormalizedData(['a' => 1, 'b' => 2]);
        $this->assertEquals(['a', 'b'], $data->keys()->all());
        $this->assertEquals([1, 2], $data->values()->all());
    }

    public function test_merge_method()
    {
        $data = new NormalizedData(['a' => 1]);
        $merged = $data->merge(['b' => 2]);
        $this->assertEquals(['a' => 1, 'b' => 2], $merged->all());
    }

    public function test_reduce_method()
    {
        $data = new NormalizedData([1, 2, 3]);
        $total = $data->reduce(fn($carry, $item) => $carry + $item, 0);
        $this->assertEquals(6, $total);
    }

    public function test_reject_method()
    {
        $data = new NormalizedData([1, 2, 3, 4]);
        $rejected = $data->reject(fn($v) => $v % 2 === 0);
        $this->assertEquals([0 => 1, 2 => 3], $rejected->all());

        $data = new NormalizedData([1, 2, null, 3]);
        $this->assertEquals([0 => 1, 1 => 2, 3 => 3], $data->reject(null)->all());
    }

    public function test_sort_methods()
    {
        $data = new NormalizedData([3, 1, 2]);
        $this->assertEquals([1 => 1, 2 => 2, 0 => 3], $data->sort()->all());

        $data = new NormalizedData([
            ['name' => 'Bob', 'age' => 30],
            ['name' => 'Alice', 'age' => 25],
        ]);

        $sorted = $data->sortBy('age');
        $this->assertEquals('Alice', $sorted->values()[0]['name']);

        $sortedDesc = $data->sortByDesc('age');
        $this->assertEquals('Bob', $sortedDesc->values()[0]['name']);
    }

    public function test_tap_and_unless_methods()
    {
        $data = new NormalizedData([1, 2, 3]);
        $tapped = false;
        $data->tap(function ($d) use (&$tapped) {
            $tapped = true;
        });
        $this->assertTrue($tapped);

        $result = $data->unless(false, fn($d) => 'callback called');
        $this->assertEquals('callback called', $result);

        $result = $data->unless(true, fn($d) => 'callback called', fn($d) => 'default called');
        $this->assertEquals('default called', $result);
    }
}
