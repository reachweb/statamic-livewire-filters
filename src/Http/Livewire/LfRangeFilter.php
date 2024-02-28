<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class LfRangeFilter extends Component
{
    use Traits\IsLivewireFilter;

    public $view = 'lf-range';

    public $selected;

    public $min;

    public $max;

    public $default;

    public $step = 1;

    public function mount($default)
    {
        $this->selected = $default;
    }

    public function dispatchEvent()
    {
        $this->dispatchFilterMounted();

        $this->dispatch('filter-updated',
            field: $this->field,
            condition: $this->condition,
            payload: $this->selected,
            command: 'replace',
            modifier: null,
        )
            ->to(LivewireCollection::class);
    }

    public function updatedSelected()
    {
        $this->dispatchEvent();
    }

    #[On('livewire:initialized')]
    public function livewireComponentReady()
    {
        $this->dispatchEvent();
    }

    #[On('preset-params')]
    public function setPresetSort($params)
    {
        $this->dispatchFilterMounted();

        if (array_key_exists($this->getParamKey(), $params)) {
            $this->selected = $params[$this->getParamKey()];
        }
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.filters.'.$this->view);
    }
}
