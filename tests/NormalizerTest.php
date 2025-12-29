<?php

namespace Elrayes\Normalizer\Tests;

use Elrayes\Normalizer\Support\Normalizer;
use Elrayes\Normalizer\Support\NormalizedData;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class NormalizerTest extends TestCase
{
    public function test_normalize_scalar_null_and_strings(): void
    {
        $n = new Normalizer();

        $this->assertNull($n->normalize(null));
        $this->assertNull($n->normalize(''));
        $this->assertNull($n->normalize('   '));

        // N/A-like values should become null (compressed mode)
        $this->assertNull($n->normalize('N/A'));
        $this->assertNull($n->normalize('n a'));
        $this->assertNull($n->normalize('n.a'));
        $this->assertNull($n->normalize(' - '));

        // Non-empty strings are trimmed and preserved
        $this->assertSame('hello', $n->normalize('  hello  '));
    }

    public function test_normalize_array_wraps_in_normalized_data(): void
    {
        $n = new Normalizer();
        $res = $n->normalize(['a' => '  x  ', 'b' => '']);

        $this->assertInstanceOf(NormalizedData::class, $res);
        $this->assertSame(['a' => 'x', 'b' => null], $res->toArray());
        $this->assertSame('x', $res['a']);
        $this->assertNull($res['b']);
    }

    public function test_normalize_object_and_collection(): void
    {
        $obj = (object) ['name' => '  John  ', 'age' => 'n/a'];
        $col = new Collection(['a' => ' 1 ', 'b' => '  ']);

        $n = new Normalizer();
        $o = $n->normalize($obj);
        $c = $n->normalize($col);

        $this->assertInstanceOf(NormalizedData::class, $o);
        $this->assertSame(['name' => 'John', 'age' => null], $o->toArray());

        $this->assertInstanceOf(NormalizedData::class, $c);
        $this->assertSame(['a' => '1', 'b' => null], $c->toArray());
    }

    public function test_config_exact_match_mode(): void
    {
        // Change config to exact mode and values
        TestConfigStore::set([
            'normalizer.na_match_mode' => 'exact',
            'normalizer.na_values' => ['N/A'], // case-insensitive via strtolower in code
        ]);

        $n = new Normalizer();
        // In exact mode, 'n a' is not exactly 'n/a' after lowercase so should NOT be null
        $this->assertSame('n a', $n->normalize('n a'));
        $this->assertNull($n->normalize('N/A'));
    }

    public function test_normalize_array_method(): void
    {
        $n = new Normalizer();
        $input = ['a' => '  trimmed  ', 'b' => 'n/a'];
        $result = $n->normalizeArray($input);

        $this->assertIsArray($result);
        $this->assertSame('trimmed', $result['a']);
        $this->assertNull($result['b']);
    }

    public function test_normalize_collection_method(): void
    {
        $n = new Normalizer();
        $input = new Collection(['a' => '  trimmed  ', 'b' => 'n/a']);
        $result = $n->normalizeCollection($input);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertSame('trimmed', $result->get('a'));
        $this->assertNull($result->get('b'));
    }
}
