<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Statamic\Tags\Context;
use Statamic\Tags\Parameters;

trait GenerateParams
{
    protected function generateParams()
    {
        $params = ($this->allowedFilters && $this->allowedFilters->isNotEmpty())
            ? $this->removeParamsNotInAllowedFiltersCollection()
            : $this->params;

        return Parameters::make(array_merge(
            ['from' => $this->collections],
            ['paginate' => $this->paginate], $params),
            Context::make([])
        );
    }

    protected function generateParamsForCount($collection, $params)
    {
        return Parameters::make(array_merge(
            ['from' => $collection],
            $params,
        ),
            Context::make([])
        );
    }

    protected function removeParamsNotInAllowedFiltersCollection()
    {
        return collect($this->params)->filter(function ($value, $key) {
            if ($key === 'sort') {
                return true;
            }
            if ($key === 'query_scope') {
                return $this->allowedFilters->contains('query_scope:'.$value);
            }

            return $this->allowedFilters->contains($key);
        })->all();
    }
}
