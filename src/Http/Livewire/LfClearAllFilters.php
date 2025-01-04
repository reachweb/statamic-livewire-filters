<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class LfClearAllFilters extends Component
{
    public $view = 'clear-all-filters';

    public $params;

    public $cleared = false;

    #[Computed]
    public function hasParams()
    {
        return $this->params && collect($this->params)->except(['sort'])->isNotEmpty();
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
