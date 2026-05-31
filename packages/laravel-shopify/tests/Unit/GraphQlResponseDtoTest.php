<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\DTOs\GraphQlResponse;
use AmadulHaque\LaravelShopify\DTOs\ThrottleStatus;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;

function httpResponse(array $payload, int $status = 200): Response
{
    return new Response(new Psr7Response($status, [], json_encode($payload)));
}

it('maps a successful payload with cost extensions', function (): void {
    $dto = GraphQlResponse::fromHttp(httpResponse([
        'data' => ['shop' => ['name' => 'Acme']],
        'extensions' => ['cost' => [
            'requestedQueryCost' => 12,
            'actualQueryCost' => 10,
            'throttleStatus' => [
                'maximumAvailable' => 1000,
                'currentlyAvailable' => 980,
                'restoreRate' => 50,
            ],
        ]],
    ]));

    expect($dto->data)->toBe(['shop' => ['name' => 'Acme']])
        ->and($dto->hasErrors())->toBeFalse()
        ->and($dto->throttled())->toBeFalse()
        ->and($dto->cost)->toBeInstanceOf(ThrottleStatus::class)
        ->and($dto->cost->currentlyAvailable)->toBe(980.0)
        ->and($dto->cost->requestedQueryCost)->toBe(12);
});

it('detects a THROTTLED error', function (): void {
    $dto = GraphQlResponse::fromHttp(httpResponse([
        'errors' => [['message' => 'Throttled', 'extensions' => ['code' => 'THROTTLED']]],
        'extensions' => ['cost' => [
            'requestedQueryCost' => 100,
            'throttleStatus' => ['maximumAvailable' => 1000, 'currentlyAvailable' => 5, 'restoreRate' => 50],
        ]],
    ]));

    expect($dto->hasErrors())->toBeTrue()
        ->and($dto->throttled())->toBeTrue();
});

it('computes restore time and backoff need from the throttle bucket', function (): void {
    $status = new ThrottleStatus(
        maximumAvailable: 1000,
        currentlyAvailable: 100,
        restoreRate: 50,
        requestedQueryCost: 0,
        actualQueryCost: 0,
    );

    expect($status->secondsToRestore(300))->toBe(4.0) // (300 - 100) / 50
        ->and($status->secondsToRestore(50))->toBe(0.0)
        ->and($status->needsBackoff(200))->toBeTrue()
        ->and($status->needsBackoff(50))->toBeFalse();
});

it('returns null cost when extensions are absent', function (): void {
    $dto = GraphQlResponse::fromHttp(httpResponse(['data' => []]));

    expect($dto->cost)->toBeNull();
});
