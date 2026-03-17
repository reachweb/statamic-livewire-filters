<?php

namespace Reach\StatamicLivewireFilters\Support;

use Illuminate\Support\Collection;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;

class CountQueryPool
{
    protected array $fieldValuesCache = [];

    public function getFieldValues(string $collection, array $baseParams, string $fieldHandle): Collection
    {
        $cacheKey = $this->cacheKey($collection, $baseParams, $fieldHandle);

        if (isset($this->fieldValuesCache[$cacheKey])) {
            return $this->fieldValuesCache[$cacheKey];
        }

        $params = Parameters::make(
            array_merge(['from' => $collection], $baseParams),
            Context::make([])
        );

        return $this->fieldValuesCache[$cacheKey] = (new CountEntries($params))->pluck($fieldHandle);
    }

    protected function cacheKey(string $collection, array $baseParams, string $fieldHandle): string
    {
        ksort($baseParams);

        return md5(serialize([
            'collection' => $collection,
            'field' => $fieldHandle,
            'params' => $baseParams,
        ]));
    }
}
