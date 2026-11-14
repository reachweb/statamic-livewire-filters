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
    /** Query params without a colon that change the result when the URL carries them. */
    protected const BARE_FILTER_KEYS = ['sort', 'order_by', 'taxonomy'];

    /** The conditions Statamic's QueriesConditions applies. It ignores a key with any other condition. */
    protected const CONDITIONS = [
        'is', 'equals', 'not', 'isnt', 'aint', '¯\\_(ツ)_/¯',
        'is_empty', 'is_blank', 'doesnt_exist', 'not_set', 'isnt_set', 'null', 'exists', 'isset',
        'contains', 'doesnt_contain', 'in', 'not_in',
        'starts_with', 'begins_with', 'doesnt_start_with', 'doesnt_begin_with', 'ends_with', 'doesnt_end_with',
        'greater_than', 'gt', 'less_than', 'lt', 'greater_than_or_equal_to', 'gte', 'less_than_or_equal_to', 'lte',
        'matches', 'match', 'regex', 'doesnt_match',
        'is_alpha', 'is_alpha_numeric', 'is_numeric', 'is_url', 'is_embeddable', 'is_email',
        'is_after', 'is_future', 'is_before', 'is_past', 'is_numberwang', 'overlaps', 'doesnt_overlap',
    ];

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
     * The active filters among URL params: `field:condition` style keys and the
     * query params without a colon that change the result. Blocked keys and empty
     * values are dropped. Other keys are tag settings and view variables, which
     * Livewire syncs to the URL with the rest of the params array.
     */
    public static function filters(array $params): array
    {
        return collect(BlockedQueryParams::strip($params))
            ->except([...LivewireCollection::TAG_ONLY_PARAMS, 'page_name', 'query_scope'])
            ->filter(fn ($value, $key) => str_contains((string) $key, ':') || in_array($key, self::BARE_FILTER_KEYS, true))
            ->reject(fn ($value) => $value === '' || $value === null || $value === [])
            ->all();
    }

    /**
     * The page query of a later page, as `[name => number]`: the highest page above 1
     * among the `seo_page_names` queries and the collection's own `page_name`, when
     * the URL carries it. Empty on a first page. Values are cast like the collection
     * casts them, so `?page=2abc` is page 2 here too.
     *
     * @return array<string, int>
     */
    public static function laterPage(Request $request, array $params = []): array
    {
        $names = (array) config('statamic-livewire-filters.seo_page_names', ['page']);

        if (is_string($params['page_name'] ?? null)) {
            $names[] = $params['page_name'];
        }

        return collect($names)
            ->filter(fn ($name) => is_string($name) && is_string($request->query($name)))
            ->mapWithKeys(fn ($name) => [$name => (int) $request->query($name)])
            ->filter(fn ($page) => $page > 1)
            ->sortDesc()
            ->take(1)
            ->all();
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

            $names = [$key, ...array_keys($aliases, $key, true)];
            $parsed = ParamKey::parse((string) $key, $scopes);

            // A field handle only covers keys the query applies. One with an unknown
            // condition is ignored, so its URL shows the same entries as the base page.
            if (in_array($parsed->condition, ['taxonomy', 'query_scope', ...self::CONDITIONS], true)) {
                $names[] = $parsed->field;
            }

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
