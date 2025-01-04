<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class LfClearAllFilters extends Component
{
    public $view = 'clear-all-filters';

    public $class;

    public $params;

    public $cleared = false;

    #[Computed]
    public function showClearButton()
    {
        return config('statamic-livewire-filters.enable_clear_all_filters')
            && $this->params
            && collect($this->params)->except(['sort'])->isNotEmpty()
            && !$this->cleared;
    }

    #[On('clear-all-params-updated')]
    public function updateParams($params)
    {
        $this->params = $params;
        $this->cleared = false;
    }

    public function clearAll()
    {
        $this->cleared = true;
        $this->dispatch('clear-all-filters');
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.ui.' . $this->view);
    }
}
