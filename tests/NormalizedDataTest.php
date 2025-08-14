<?php

namespace Elrayes\Normalizer\Tests;

use Elrayes\Normalizer\Support\NormalizedData;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class NormalizedDataTest extends TestCase
{
    public function test_array_and_object_access_and_dot_notation(): void
    {
        $data = new NormalizedData([
            'user' => [
                'name' => 'John',
                'profile' => ['first' => 'J', 'last' => 'D'],
            ],
            'date.upload' => '2020-01-01', // an exact key containing dot
            'a' => ['b' => ['c' => 123]],
        ]);

        // Exact key precedence
        $this->assertSame('2020-01-01', $data['date.upload']);
        $this->assertSame('2020-01-01', $data->get('date.upload'));

        // Dot-notation for nested
        $this->assertSame(123, $data['a.b.c']);
        $this->assertSame('John', $data['user.name']);
        $this->assertSame('D', $data['user.profile.last']);

        // Property access uses get()
        $this->assertInstanceOf(NormalizedData::class, $data->user);
        $this->assertSame('John', $data->user['name']);

        // Escaped dot: should search for literal key
        $this->assertSame('2020-01-01', $data['date\.upload']);

        // Non-existing => null
        $this->assertFalse(isset($data['unknown']));
        $this->assertNull($data['unknown']);
    }

    public function test_to_array_and_to_object(): void
    {
        $innerObj = (object) ['k' => 'v'];
        $coll = new Collection(['x' => 1, 'y' => 2]);
        $data = new NormalizedData([
            'arr' => ['a' => 1, 'b' => ['c' => 2]],
            'obj' => $innerObj,
            'col' => $coll,
            'list' => [1, 2, 3],
        ]);

        $array = $data->toArray();
        $this->assertSame(['a' => 1, 'b' => ['c' => 2]], $array['arr']);
        $this->assertSame(['k' => 'v'], $array['obj']);
        $this->assertSame(['x' => 1, 'y' => 2], $array['col']);
        $this->assertSame([1,2,3], $array['list']);

        $objectified = $data->toObject();
        // objectified->arr should be stdClass with nested stdClass
        $this->assertIsObject($objectified->arr);
        $this->assertSame(2, $objectified->arr->b->c);
        // Collections become arrays first, then associative arrays become stdClass
        $this->assertIsObject($objectified->col);
        $this->assertSame(1, $objectified->col->x);
        $this->assertIsArray($objectified->list);
    }

    public function test_count_and_iteration_and_set(): void
    {
        $data = new NormalizedData(['a' => 1]);
        $this->assertCount(1, $data);
        $data['b'] = ['c' => 2];
        $this->assertCount(2, $data);
        $this->assertSame(['a' => 1, 'b' => ['c' => 2]], $data->toArray());

        $keys = [];
        foreach ($data as $k => $v) {
            $keys[] = $k;
        }
        $this->assertSame(['a','b'], $keys);

        unset($data['a']);
        $this->assertFalse(isset($data['a']));
    }
}
