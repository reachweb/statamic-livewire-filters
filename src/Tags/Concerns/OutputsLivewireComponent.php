<?php

namespace Reach\StatamicLivewireFilters\Tags\Concerns;

use Livewire\Livewire;

trait OutputsLivewireComponent
{
    public function renderLivewireComponent($name, $params = [])
    {
        return Livewire::mount($name, [$params]);
    }
}
