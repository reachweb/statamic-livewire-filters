<?php

namespace Reach\StatamicLivewireFilters\Support;

use Illuminate\Http\Request;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;

/**
 * Indexing rules for filtered listing URLs, shared by {{ livewire-filters:seo }}
 * and the custom query string SEO headers.
 */
class Seo
{
    /**
     * The `params` query of the Livewire query string mode, the only mode whose
     * filter state the tag reads from the URL.
     */
    public static function livewireParams(Request $request): array
    {
        $params = CustomQueryString::livewireQueryStringEnabled() ? $request->query('params') : null;

        return is_array($params) ? $params : [];
    }

    /**
     * The active filters among URL params: blocked keys, collection settings
     * (Livewire syncs the whole params array to the URL) and empty values are dropped.
     */
    public static function filters(array $params): array
    {
        return collect(BlockedQueryParams::strip($params))
            ->except([...LivewireCollection::TAG_ONLY_PARAMS, 'page_name', 'query_scope'])
            ->reject(fn ($value) => $value === '' || $value === null || $value === [])
            ->all();
    }

    /**
     * The highest page number among the `seo_page_names` queries and the
     * collection's own `page_name`, when the URL carries it.
     */
    public static function page(Request $request, array $params = []): int
    {
        $names = (array) config('statamic-livewire-filters.seo_page_names', ['page']);

        if (is_string($params['page_name'] ?? null)) {
            $names[] = $params['page_name'];
        }

        return collect($names)
            ->filter(fn ($name) => is_string($name) && is_numeric($request->query($name)))
            ->map(fn ($name) => (int) $request->query($name))
            ->push(1)
            ->max();
    }

    /**
     * Whether a filtered first page stays indexable: every active filter is
     * single-valued and listed in `seo_indexable_filters` by alias, key or field.
     */
    public static function indexableFilters(array $filters, array $params): bool
    {
        $indexable = (array) config('statamic-livewire-filters.seo_indexable_filters', []);

        if ($filters === [] || $indexable === []) {
            return false;
        }

        $scopes = static::scopes($params);
        $aliases = CustomQueryString::aliases();

        foreach ($filters as $key => $value) {
            if (! is_string($value) || str_contains($value, '|')) {
                return false;
            }

            $names = [$key, ParamKey::parse((string) $key, $scopes)->field, ...array_keys($aliases, $key, true)];

            if (array_intersect($names, $indexable) === []) {
                return false;
            }
        }

        return true;
    }

    /**
     * Filters as the canonical `params` query: sorted, with only the scopes they use.
     */
    public static function canonicalParams(array $filters, array $params): array
    {
        ksort($filters);

        $usedScopes = collect(array_keys($filters))
            ->map(fn ($key) => ParamKey::parse((string) $key, static::scopes($params)))
            ->filter(fn (ParamKey $key) => $key->condition === 'query_scope')
            ->map(fn (ParamKey $key) => $key->modifier)
            ->unique()
            ->sort()
            ->implode('|');

        return $usedScopes === '' ? $filters : [...$filters, 'query_scope' => $usedScopes];
    }

    /**
     * Filters as custom query string segments, in the order of the configured aliases.
     */
    public static function canonicalSegments(array $filters): string
    {
        return collect(CustomQueryString::aliases())
            ->unique()
            ->filter(fn ($key) => array_key_exists($key, $filters))
            ->map(fn ($key, $alias) => rawurlencode($alias).'/'.rawurlencode($filters[$key]))
            ->implode('/');
    }

    /**
     * Registered query scopes listed in the params, so a crafted `scope:field`
     * key can't pass as a filter on another field.
     */
    protected static function scopes(array $params): array
    {
        $scopes = $params['query_scope'] ?? '';

        if (! is_string($scopes)) {
            return [];
        }

        return array_values(array_filter(explode('|', $scopes), fn ($scope) => app('statamic.scopes')->has($scope)));
    }
}
