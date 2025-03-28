<?php

namespace Reach\StatamicLivewireFilters\Tags\Concerns;

use Livewire\Livewire;

trait OutputsLivewireComponent
{
    public function renderLivewireComponent($name, $params = [])
    {
        // Define special parameters to extract
        $specialParams = ['lazy', 'scrollTo'];

        $options = collect($specialParams)
            ->filter(fn ($param) => $this->params->has($param))
            ->mapWithKeys(fn ($param) => [$param => $this->params->get($param)])
            ->all();

        $filteredParams = collect($params)
            ->except($specialParams)
            ->all();

        return Livewire::mount($name, array_merge(['params' => $filteredParams], $options));
    }
}
