<?php

namespace Amadulhaque\Shopify\Authentication;

use Illuminate\Support\Str;

class RandomStateGenerator implements StateGenerator
{
    public function generate(): string
    {
        return Str::random(64);
    }
}
