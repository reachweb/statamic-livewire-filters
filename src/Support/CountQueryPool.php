<?php

namespace Reach\StatamicLivewireFilters\Support;

use Illuminate\Support\Collection;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder as StacheEntryQueryBuilder;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;

class CountQueryPool
{
    protected array $fieldValuesCache = [];

    protected array $keySetCache = [];

    /** @var array<string, array<int, array{0: string, 1: ?string}>> facet specs by filter collection */
    protected array $registeredFacets = [];

    /** @var array<int, string|array> the collections of the collection components mounted in this request */
    protected array $mountedCollections = [];

    protected array $prefetched = [];

    public const MAX_FACETS = 64;

    public function registerFacet(string $collection, array $spec): void
    {
        if (! in_array($spec, $this->registeredFacets[$collection] ?? [], true)) {
            $this->registeredFacets[$collection][] = $spec;
        }
    }

    /**
     * Records a collection component's mount and returns the facets registered so far
     * by filters on the collections it queries.
     *
     * @return array<int, array{0: string, 1: ?string}>
     */
    public function collectionMounted(string|array|null $collections): array
    {
        $this->mountedCollections[] = $collections;

        return collect($this->registeredFacets)
            ->filter(fn ($specs, $collection) => self::queries($collections, $collection))
            ->flatten(1)
            ->unique(fn ($spec) => serialize($spec))
            ->take(self::MAX_FACETS)
            ->values()
            ->all();
    }

    public function collectionMountedFor(string $collection): bool
    {
        return collect($this->mountedCollections)->contains(fn ($collections) => self::queries($collections, $collection));
    }

    public static function queries(string|array|null $collections, string $collection): bool
    {
        if ($collections === '*') {
            return true;
        }

        return in_array($collection, is_string($collections) ? explode('|', $collections) : (array) $collections, true);
    }

    /**
     * Facet engine: fills the field cache for every facet that shares base params with
     * another, with one query per filter state instead of one per field. getFieldValues()
     * then serves them; values only ever leave the pool through it.
     *
     * @param  array  $params  the authenticated params, `from` holding the tag's collections
     * @param  array<int, array{0: string, 1: ?string}>  $specs
     */
    public function prefetch(array $params, array $specs): void
    {
        // The Stache already shares one key set per filter state and reads fields from their indexes.
        if (Entry::query() instanceof StacheEntryQueryBuilder) {
            return;
        }

        $specs = array_slice($specs, 0, self::MAX_FACETS + 1);
        $memo = md5(serialize([$params, $specs]));

        if (isset($this->prefetched[$memo])) {
            return;
        }

        $this->prefetched[$memo] = true;

        $groups = [];

        foreach ($specs as [$field, $scope]) {
            $collections = FacetSpec::collectionsWithField($params['from'] ?? [], $field);

            if ($collections === []) {
                continue;
            }

            $baseParams = BlockedQueryParams::rejectDynamicBindings(FacetSpec::baseParams($params, $field, $scope));
            unset($baseParams['from']);

            if (isset($this->fieldValuesCache[$this->cacheKey($collections, $baseParams, $field)])) {
                continue;
            }

            $groupKey = $this->cacheKey($collections, $baseParams, null);
            $groups[$groupKey]['collections'] = $collections;
            $groups[$groupKey]['params'] = $baseParams;
            $groups[$groupKey]['fields'][$field] = true;
        }

        foreach ($groups as $groupKey => $group) {
            if (count($group['fields']) < 2) {
                continue;
            }

            $entries = new CountEntries(Parameters::make(
                array_merge($group['params'], ['from' => $group['collections']]),
                Context::make([])
            ));

            // The Stache reads each field from its index once the key set is shared.
            if (! array_key_exists($groupKey, $this->keySetCache)) {
                $this->keySetCache[$groupKey] = $entries->resolvedKeys();
            }

            if ($this->keySetCache[$groupKey] !== null) {
                continue;
            }

            $values = $entries->pluckMany(array_keys($group['fields']));

            foreach ($values ?? [] as $field => $fieldValues) {
                $this->fieldValuesCache[$this->cacheKey($group['collections'], $group['params'], $field)] = $fieldValues;
            }
        }
    }

    public function getFieldValues(string|array $collection, array $baseParams, string $fieldHandle): Collection
    {
        $baseParams = BlockedQueryParams::rejectDynamicBindings($baseParams);

        // $collection always wins the `from` merge below, so a `from` in the params
        // must not split the caches between the counts and the bounds listeners.
        unset($baseParams['from']);

        $cacheKey = $this->cacheKey($collection, $baseParams, $fieldHandle);

        if (isset($this->fieldValuesCache[$cacheKey])) {
            return $this->fieldValuesCache[$cacheKey];
        }

        $entries = new CountEntries(Parameters::make(
            array_merge($baseParams, ['from' => $collection]),
            Context::make([])
        ));

        // Listeners sharing the same base params share one filter pass; each field
        // then costs an index read instead of a query.
        $keySetKey = $this->cacheKey($collection, $baseParams, null);

        if (! array_key_exists($keySetKey, $this->keySetCache)) {
            $this->keySetCache[$keySetKey] = $entries->resolvedKeys();
        }

        $keys = $this->keySetCache[$keySetKey];

        return $this->fieldValuesCache[$cacheKey] = $keys === null
            ? $entries->pluck($fieldHandle)
            : CountEntries::valuesForKeys($keys, $fieldHandle);
    }

    protected function cacheKey(string|array $collection, array $baseParams, ?string $fieldHandle): string
    {
        ksort($baseParams);

        return md5(serialize([
            'collection' => $collection,
            'field' => $fieldHandle,
            'params' => $baseParams,
        ]));
    }
}
