<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class LfTextFilter extends Component
{
    use Traits\IsLivewireFilter;

    public $view = 'lf-text';

    public $selected = '';

    public function updatedSelected()
    {
        $this->dispatch('filter-updated',
            field: $this->field,
            condition: $this->condition,
            payload: $this->selected,
            command: 'replace',
            modifier: $this->modifier,
        )
            ->to(LivewireCollection::class);
    }

    public function clear()
    {
        $this->selected = '';
        $this->clearFilters();
    }

    #[On('preset-params')]
    public function setPresetSort($params)
    {
        if (array_key_exists($this->getParamKey(), $params)) {
            $this->selected = $params[$this->getParamKey()];
        }
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.filters.'.$this->view);
    }
}
