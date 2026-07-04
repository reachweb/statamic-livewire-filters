<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Illuminate\Support\Str;
use Reach\StatamicLivewireFilters\Support\BlockedQueryParams;
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
        $allowed = ($this->allowedFilters && $this->allowedFilters->isNotEmpty())
            ? $this->allowedFilters
            : null;

        return array_merge(
            BlockedQueryParams::strip($params, $allowed),
            $this->trustedQueryParams,
        );
    }

    protected function captureTrustedQueryParams(array $tagParams): void
    {
        $blocked = collect(config('statamic-livewire-filters.blocked_query_params', []));

        $this->trustedQueryParams = collect($tagParams)
            ->only(array_keys($this->params))
            ->reject(fn ($value, $key) => ! $blocked->contains(Str::before($key, ':')))
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
