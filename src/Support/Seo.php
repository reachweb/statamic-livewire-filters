<?php

namespace Reach\StatamicLivewireFilters\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Reach\StatamicLivewireFilters\Http\Middleware\HandleFiltersQueryString;

/**
 * Indexing rules for filtered listing URLs, shared by {{ livewire-filters:seo }}
 * and the custom query string SEO headers.
 */
class Seo
{
    /**
     * Filter params that come from the visible URL in the active URL mode.
     */
    public static function urlParams(Request $request): array
    {
        if (CustomQueryString::livewireQueryStringEnabled()) {
            $params = $request->query('params');
        } elseif ($request->attributes->has(HandleFiltersQueryString::ORIGINAL_PATH_ATTRIBUTE)) {
            $params = $request->input('params');
        } else {
            return [];
        }

        return is_array($params) ? $params : [];
    }

    /**
     * The active filters among the URL params, without query scope bookkeeping.
     */
    public static function filters(array $urlParams): array
    {
        return collect($urlParams)
            ->except('query_scope')
            ->reject(fn ($value) => $value === '' || $value === null || $value === [])
            ->all();
    }

    public static function page(Request $request): int
    {
        return max(1, (int) $request->query('page', 1));
    }

    /**
     * Whether a filtered first page stays indexable: every active filter is
     * single-valued and listed in `seo_indexable_filters`.
     */
    public static function indexableFilters(array $filters, array $urlParams): bool
    {
        $indexable = (array) config('statamic-livewire-filters.seo_indexable_filters', []);

        if ($filters === [] || $indexable === []) {
            return false;
        }

        $scopes = explode('|', (string) ($urlParams['query_scope'] ?? ''));

        $aliases = collect(config('statamic-livewire-filters.custom_query_string_aliases', []))
            ->map(fn ($key) => Str::after($key, 'query_scope:'));

        foreach ($filters as $key => $value) {
            if (! is_string($value) || str_contains($value, '|') || str_contains($value, ',')) {
                return false;
            }

            $segments = explode(':', (string) $key);
            $field = $segments[0] === 'taxonomy' || in_array($segments[0], $scopes, true)
                ? ($segments[1] ?? $segments[0])
                : $segments[0];

            $names = $aliases->filter(fn ($actualKey) => $actualKey === $key)->keys()
                ->push($key, $field)
                ->all();

            if (array_intersect($names, $indexable) === []) {
                return false;
            }
        }

        return true;
    }
}
