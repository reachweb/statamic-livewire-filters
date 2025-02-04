<?php

namespace Reach\StatamicLivewireFilters\Tags\Concerns;

use Livewire\Livewire;

trait OutputsLivewireComponent
{
    public function renderLivewireComponent($name, $params = [])
    {
        if ($this->params->has('lazy') && $this->params['lazy'] !== false) {
            unset($params['lazy']);

            return Livewire::mount($name, [$params, 'lazy' => true]);
        }

        return Livewire::mount($name, [$params]);
    }
}
