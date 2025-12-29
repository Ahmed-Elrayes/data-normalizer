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

    public function test_map_functionality(): void
    {
        $data = new NormalizedData([
            'item1' => 10,
            'item2' => 20,
            'nested' => ['a' => 1]
        ]);

        $mapped = $data->map(function ($value, $key) {
            if (is_numeric($value)) {
                return $value * 2;
            }
            return $value;
        });

        $this->assertInstanceOf(NormalizedData::class, $mapped);
        $this->assertSame(20, $mapped['item1']);
        $this->assertSame(40, $mapped['item2']);
        $this->assertInstanceOf(NormalizedData::class, $mapped->get('nested'));
        $this->assertSame(2, $mapped['nested.a']);

        // returns new instance
        $this->assertNotSame($data, $mapped);
    }

    public function test_recursive_map_functionality(): void
    {
        $data = new NormalizedData([
            'a' => 1,
            'b' => [
                'c' => 2,
                'd' => [
                    'e' => 3
                ]
            ]
        ]);

        $mapped = $data->map(function ($value, $key) {
            if (is_numeric($value)) {
                return $value + 10;
            }
            return $value;
        });

        $this->assertSame(11, $mapped['a']);
        $this->assertSame(12, $mapped['b.c']);
        $this->assertSame(13, $mapped['b.d.e']);
    }

    public function test_to_json_functionality(): void
    {
        $data = new NormalizedData([
            'name' => 'John',
            'age' => 30,
            'nested' => ['key' => 'value']
        ]);

        $json = $data->toJson();
        $this->assertJson($json);
        $this->assertSame(json_encode(['name' => 'John', 'age' => 30, 'nested' => ['key' => 'value']]), $json);

        // test with options
        $jsonPretty = $data->toJson(JSON_PRETTY_PRINT);
        $this->assertSame(json_encode(['name' => 'John', 'age' => 30, 'nested' => ['key' => 'value']], JSON_PRETTY_PRINT), $jsonPretty);

        // test toPrettyJson
        $this->assertSame($jsonPretty, $data->toPrettyJson());
    }

    public function test_all_method(): void
    {
        $items = ['a' => 1, 'b' => 2];
        $data = new NormalizedData($items);
        $this->assertSame($items, $data->all());
    }

    public function test_offset_exists_with_dot_notation(): void
    {
        $data = new NormalizedData([
            'a' => ['b' => 1],
            'c.d' => 2,
        ]);

        $this->assertTrue(isset($data['a.b']));
        $this->assertTrue(isset($data['c.d']));
        $this->assertTrue(isset($data['c\.d']));
        $this->assertFalse(isset($data['a.c']));
        $this->assertFalse(isset($data['x']));
    }

    public function test_offset_unset(): void
    {
        $data = new NormalizedData(['a' => 1, 'b' => 2]);
        unset($data['a']);
        $this->assertFalse(isset($data['a']));
        $this->assertCount(1, $data);
    }

    public function test_to_object_deep(): void
    {
        $data = new NormalizedData([
            'user' => [
                'name' => 'John',
                'tags' => ['php', 'laravel'],
                'meta' => [
                    'last_login' => '2023-01-01'
                ]
            ],
            'items' => [
                ['id' => 1, 'val' => 'a'],
                ['id' => 2, 'val' => 'b'],
            ]
        ]);

        $obj = $data->toObject();

        $this->assertInstanceOf(\stdClass::class, $obj);
        $this->assertInstanceOf(\stdClass::class, $obj->user);
        $this->assertSame('John', $obj->user->name);
        $this->assertIsArray($obj->user->tags);
        $this->assertSame('php', $obj->user->tags[0]);
        $this->assertInstanceOf(\stdClass::class, $obj->user->meta);
        $this->assertSame('2023-01-01', $obj->user->meta->last_login);
        
        $this->assertIsArray($obj->items);
        $this->assertInstanceOf(\stdClass::class, $obj->items[0]);
        $this->assertSame(1, $obj->items[0]->id);
    }
}
