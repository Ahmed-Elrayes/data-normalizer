<?php

namespace Elrayes\Normalizer\Facades;

use Elrayes\Normalizer\Support\NormalizedData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static NormalizedData|mixed normalize(mixed $value)
 * @method static array normalizeArray(array|Collection $input)
 * @method static Collection normalizeCollection(Collection $input)
 */
class Normalizer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'normalizer';
    }
}
