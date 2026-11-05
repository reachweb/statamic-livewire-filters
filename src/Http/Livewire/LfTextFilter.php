<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\Locked;

class LfTextFilter extends LivewireFilter
{
    #[Locked]
    public $view = 'lf-text';

    public $placeholder = '';

    public $selected = '';
}
