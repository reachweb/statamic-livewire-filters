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

    public function mount($blueprint, $field, $condition, $min, $max, $default)
    {
        [$collection, $blueprint] = explode('.', $blueprint);
        $this->collection = $collection;
        $this->blueprint = $blueprint;
        $this->field = $field;
        $this->condition = $condition;
        $this->min = $min;
        $this->max = $max;
        $this->selected = $default;

        $this->initiateField();
    }

    public function dispatchEvent()
    {
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
        if (array_key_exists($this->getParamKey(), $params)) {
            $this->selected = $params[$this->getParamKey()];
        }
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.filters.'.$this->view);
    }
}
