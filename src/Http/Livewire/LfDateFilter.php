<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

class LfDateFilter extends LivewireFilter
{
    #[Locked]
    public $view = 'lf-date';

    public $selected = '';

    public $placeholder = '';

    #[Computed]
    public function filterOptions()
    {
        $fieldOptions = collect($this->statamic_field);

        return $fieldOptions->only(['earliest_date', 'latest_date'])->all();
    }
}
