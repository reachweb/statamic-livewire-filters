<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class LfTextFilter extends Component
{
    use Traits\IsLivewireFilter;

    public $view = 'lf-text';

    public $placeholder = '';

    public $selected = '';

    public function updatedSelected()
    {
        $this->dispatch('filter-updated',
            field: $this->field,
            condition: $this->condition,
            payload: $this->selected,
            modifier: $this->modifier,
        )
            ->to(LivewireCollection::class);
    }

    #[On('clear-all-filters')]
    public function clear()
    {
        $this->selected = '';
        $this->clearFilters();
    }

    #[On('clear-option')]
    public function clearOption($tag)
    {
        if ($tag['field'] === $this->field) {
            $this->clear();
        }
    }

    #[On('preset-params')]
    public function setPresetValues($params)
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
