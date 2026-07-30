<?php

namespace Amadulhaque\Shopify\Authentication;

interface StateGenerator
{
    public function generate(): string;
}
