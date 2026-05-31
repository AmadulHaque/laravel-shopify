<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\ValueObjects\Scopes;

it('parses, normalizes and sorts a csv scope string', function (): void {
    $scopes = Scopes::fromString('write_products, read_products ,read_products');

    expect($scopes->scopes)->toBe(['read_products', 'write_products'])
        ->and($scopes->toString())->toBe('read_products,write_products')
        ->and((string) $scopes)->toBe('read_products,write_products');
});

it('checks membership', function (): void {
    $scopes = Scopes::fromString('read_orders,write_orders');

    expect($scopes->has('read_orders'))->toBeTrue()
        ->and($scopes->has(' write_orders '))->toBeTrue()
        ->and($scopes->has('read_products'))->toBeFalse();
});

it('compares equality regardless of order', function (): void {
    expect(Scopes::fromString('a,b')->equals(Scopes::fromString('b,a')))->toBeTrue()
        ->and(Scopes::fromString('a,b')->equals(Scopes::fromString('a,c')))->toBeFalse();
});

it('diffs scopes that were granted previously', function (): void {
    $requested = Scopes::fromString('read_products,write_products,read_orders');
    $granted = Scopes::fromString('read_products,write_products');

    expect($requested->diff($granted)->scopes)->toBe(['read_orders']);
});

it('treats an empty string as no scopes', function (): void {
    expect(Scopes::fromString('')->isEmpty())->toBeTrue();
});
