<?php

namespace Reach\StatamicLivewireFilters\Tags\Concerns;

use Livewire\Livewire;

trait OutputsLivewireComponent
{
    public function renderLivewireComponent($name, $params = [], $key = null)
    {
        return Livewire::mount($name, [$params], $key);
    }
}
