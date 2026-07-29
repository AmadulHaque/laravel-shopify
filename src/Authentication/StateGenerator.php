<?php

namespace Decoupled\Shopify\Authentication;

interface StateGenerator
{
    public function generate(): string;
}
