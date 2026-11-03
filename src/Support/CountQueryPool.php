<?php

namespace Reach\StatamicLivewireFilters\Support;

use Illuminate\Support\Collection;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;

class CountQueryPool
{
    protected array $fieldValuesCache = [];

    protected array $keySetCache = [];

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
