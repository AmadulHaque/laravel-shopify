<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\ValueObjects;

use Stringable;

/**
 * An immutable set of Shopify access scopes (e.g. "read_products", "write_orders").
 *
 * Stored as a comma-separated string on the model but manipulated as a typed
 * value object so scope comparisons (re-auth detection) never touch raw strings.
 */
final readonly class Scopes implements Stringable
{
    /**
     * @var list<string>
     */
    public array $scopes;

    /**
     * @param  iterable<string>  $scopes
     */
    public function __construct(iterable $scopes)
    {
        $normalized = [];

        foreach ($scopes as $scope) {
            $scope = trim($scope);

            if ($scope !== '') {
                $normalized[$scope] = true;
            }
        }

        $keys = array_keys($normalized);
        sort($keys);

        $this->scopes = $keys;
    }

    public static function fromString(string $csv): self
    {
        return new self(explode(',', $csv));
    }

    public function toString(): string
    {
        return implode(',', $this->scopes);
    }

    public function has(string $scope): bool
    {
        return in_array(trim($scope), $this->scopes, true);
    }

    /**
     * Scopes present in this set but missing from the other.
     */
    public function diff(self $other): self
    {
        return new self(array_diff($this->scopes, $other->scopes));
    }

    public function equals(self $other): bool
    {
        return $this->scopes === $other->scopes;
    }

    public function isEmpty(): bool
    {
        return $this->scopes === [];
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
