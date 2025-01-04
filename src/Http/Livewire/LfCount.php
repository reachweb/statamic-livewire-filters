<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class LfCount extends Component
{
    public $count;

    public $view = 'count';

    #[On('total-count-updated')]
    public function updatedCount($count)
    {
        $this->count = $count;
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.ui.' . $this->view);
    }
}
