<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Statamic\Tags\Context;
use Statamic\Tags\Parameters;

trait GenerateParams
{
    protected function generateParams()
    {
        $params = $this->removeParamsNotInFiltersCollection();

        return Parameters::make(array_merge(
            ['from' => $this->collections],
            ['paginate' => $this->paginate], $params),
            Context::make([])
        );
    }

    protected function removeParamsNotInFiltersCollection()
    {
        return collect($this->params)->filter(function ($value, $key) {
            if ($key === 'sort') {
                return true;
            }
            if ($key === 'query_scope') {
                return $this->filters->contains('query_scope:'.$value);
            }

            return $this->filters->contains($key);
        })->all();
    }
}
