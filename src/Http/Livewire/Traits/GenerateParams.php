<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Statamic\Tags\Context;
use Statamic\Tags\Parameters;

trait GenerateParams
{
    protected function generateParams()
    {
        return Parameters::make(array_merge(['from' => $this->collections], $this->params), Context::make([]));
    }
}
