<?php

namespace Reach\StatamicLivewireFilters\Support;

/**
 * The filter param-key grammar shared by the filters, the collection and the tags:
 * `field:condition`, `taxonomy:handle:modifier`, `scope:field`, and dual-range
 * `field:min|field:max` pairs whose comparisons come from the modifier.
 */
final class ParamKey
{
    private function __construct(
        public readonly string $field,
        public readonly string $condition,
        public readonly ?string $modifier = null,
    ) {}

    /**
     * @return string|array{min: string, max: string}
     */
    public static function build(string $field, ?string $condition, ?string $modifier = 'any'): string|array
    {
        if ($condition === 'taxonomy') {
            return 'taxonomy:'.$field.':'.$modifier;
        }

        if ($condition === 'query_scope') {
            return $modifier.':'.$field;
        }

        if ($condition === 'dual_range') {
            [$min, $max] = $modifier === null || $modifier === 'any' ? ['gte', 'lte'] : explode('|', $modifier);

            return [
                'min' => $field.':'.$min,
                'max' => $field.':'.$max,
            ];
        }

        return $field.':'.$condition;
    }

    /**
     * The other key of a match-any / match-all pair, which the checkbox filter's logic
     * toggle switches between: `taxonomy:field:any` and `taxonomy:field:all`, or
     * `field:overlaps` and `field:contains_all`. Null for keys outside these pairs.
     */
    public static function logicCounterpart(string $key): ?string
    {
        $parsed = self::parse($key);

        return match (true) {
            $parsed->condition === 'taxonomy' && $parsed->modifier === 'any' => self::build($parsed->field, 'taxonomy', 'all'),
            $parsed->condition === 'taxonomy' && $parsed->modifier === 'all' => self::build($parsed->field, 'taxonomy', 'any'),
            $parsed->condition === 'overlaps' => self::build($parsed->field, 'contains_all'),
            $parsed->condition === 'contains_all' => self::build($parsed->field, 'overlaps'),
            default => null,
        };
    }

    public static function isMatchAll(string $key): bool
    {
        $parsed = self::parse($key);

        return $parsed->condition === 'contains_all'
            || ($parsed->condition === 'taxonomy' && $parsed->modifier === 'all');
    }

    /**
     * A `scope:field` key reads like `field:condition`, so scope keys are only
     * recognized when their scope is listed in $scopes (the query_scope registry).
     *
     * @param  array<int, string>  $scopes
     */
    public static function parse(string $key, array $scopes = []): self
    {
        $parts = explode(':', $key);

        if ($parts[0] === 'taxonomy' && count($parts) >= 3) {
            return new self($parts[1], 'taxonomy', $parts[2]);
        }

        if (in_array($parts[0], $scopes, true)) {
            return new self($parts[1] ?? '', 'query_scope', $parts[0]);
        }

        return new self($parts[0], $parts[1] ?? '');
    }
}
