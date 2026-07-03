<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Illuminate\Support\Str;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;

trait GenerateParams
{
    protected function generateParams()
    {
        $params = ($this->allowedFilters && $this->allowedFilters->isNotEmpty())
            ? $this->removeParamsNotInAllowedFiltersCollection()
            : $this->params;

        $params = $this->removeBlockedQueryParams($params);

        return Parameters::make(array_merge(
            ['from' => $this->collections],
            ['paginate' => $this->paginate], $params),
            Context::make([])
        );
    }

    protected function removeBlockedQueryParams(array $params): array
    {
        $blocked = collect(config('statamic-livewire-filters.blocked_query_params', []));

        if ($blocked->isEmpty()) {
            return $params;
        }

        $allowed = ($this->allowedFilters && $this->allowedFilters->isNotEmpty())
            ? $this->allowedFilters
            : collect();

        // Match on the segment before the first colon so both bare control keys
        // (`limit`) and condition-style visibility params (`status:is`) are caught,
        // while `field:condition` filter params on real fields pass through.
        return collect($params)
            ->reject(fn ($value, $key) => ! $allowed->contains($key) && $blocked->contains(Str::before($key, ':')))
            ->all();
    }

    protected function removeParamsNotInAllowedFiltersCollection()
    {
        return collect($this->params)->filter(function ($value, $key) {
            // page_name is collection config, not a filter — it must always reach the
            // Entries tag so Statamic paginates under the same name the addon resets.
            if ($key === 'sort' || $key === 'page_name') {
                return true;
            }
            if ($key === 'query_scope') {
                return $this->allowedFilters->contains('query_scope:'.$value);
            }

            return $this->allowedFilters->contains($key);
        })->all();
    }
}
